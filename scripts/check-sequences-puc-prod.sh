#!/usr/bin/env bash
#
# Vérifie que toutes les colonnes "id" entières (integer/bigint/smallint) de
# la base ont bien une séquence d'auto-incrémentation branchée en DEFAULT.
# Sans ça, toute insertion dans une table oubliée échoue avec
# "null value in column id violates not-null constraint" (voir runbook §6.4).
#
# Usage :
#   ./check-sequences-puc-prod.sh            # affiche les tables à problème
#   ./check-sequences-puc-prod.sh --fix       # + corrige automatiquement
#
# À lancer depuis /srv/puc_prod après toute migration.
#
# Différences par rapport à la version précédente :
#   - les colonnes IDENTITY (GENERATED ... AS IDENTITY) sont ignorées : elles
#     n'ont pas de DEFAULT dans pg_attrdef mais fonctionnent très bien, et le
#     SET DEFAULT tenté par --fix échouait dessus ;
#   - les tables partitionnées (relkind 'p') sont vérifiées ;
#   - les colonnes supprimées sont ignorées ;
#   - le SQL passe par stdin avec des variables psql (:"schema", :'seq'), donc
#     les identifiants sont correctement quotés ;
#   - plus de `docker compose exec` dans une boucle `while read` : il lisait
#     stdin et avalait les lignes restantes, si bien que seule la PREMIÈRE
#     table était corrigée quand plusieurs étaient en défaut ;
#   - chaque correction est atomique (BEGIN/COMMIT).

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/srv/puc_prod}"
DC="docker compose --env-file ${PROJECT_DIR}/.env --env-file ${PROJECT_DIR}/.env.prod --env-file ${PROJECT_DIR}/.env.local"
SCHEMAS=("public" "divercity")

FIX=0
case "${1:-}" in
    "")    ;;
    --fix) FIX=1 ;;
    *)     echo "Usage : $0 [--fix]"; exit 2 ;;
esac

cd "$PROJECT_DIR"

# Exécute psql dans le conteneur postgres ; le SQL est lu sur stdin,
# les arguments (-v var=valeur, -t, etc.) sont transmis tels quels.
run_psql() {
    $DC exec -T postgres sh -c \
        'exec psql -X -U "$POSTGRES_USER" -d "$POSTGRES_DB" -v ON_ERROR_STOP=1 "$@"' _ "$@"
}

TOTAL_FOUND=0

for SCHEMA in "${SCHEMAS[@]}"; do
    echo "==> Schéma : ${SCHEMA}"

    RESULT="$(run_psql -q -t -A -F ',' -v "schema=${SCHEMA}" <<'SQL'
SELECT c.relname, a.attname, format_type(a.atttypid, a.atttypmod)
FROM pg_class c
JOIN pg_attribute a
  ON a.attrelid = c.oid AND a.attname = 'id' AND NOT a.attisdropped
LEFT JOIN pg_attrdef d
  ON d.adrelid = c.oid AND d.adnum = a.attnum
WHERE c.relkind IN ('r', 'p')
  AND c.relnamespace = to_regnamespace(:'schema')
  AND d.adbin IS NULL
  AND a.attidentity NOT IN ('a', 'd')
  AND a.attnotnull
  AND format_type(a.atttypid, a.atttypmod) IN ('integer', 'bigint', 'smallint')
ORDER BY c.relname;
SQL
)"

    if [ -z "$RESULT" ]; then
        echo "    OK — aucune table à problème."
        continue
    fi

    mapfile -t ROWS <<< "$RESULT"

    for ROW in "${ROWS[@]}"; do
        [ -z "$ROW" ] && continue
        IFS=',' read -r TABLE COLUMN TYPE <<< "$ROW"
        TOTAL_FOUND=$((TOTAL_FOUND + 1))
        echo "    MANQUANT : ${SCHEMA}.${TABLE} (colonne ${COLUMN}, type ${TYPE})"

        if [ "$FIX" -eq 1 ]; then
            echo "       -> Correction en cours..."
            run_psql -q -v "schema=${SCHEMA}" -v "tbl=${TABLE}" -v "seq=${TABLE}_id_seq" <<'SQL'
BEGIN;
SELECT format('%I.%I', :'schema', :'seq') AS qseq \gset
CREATE SEQUENCE IF NOT EXISTS :"schema".:"seq";
ALTER SEQUENCE :"schema".:"seq" OWNED BY :"schema".:"tbl".id;
ALTER TABLE :"schema".:"tbl" ALTER COLUMN id SET DEFAULT nextval(:'qseq'::regclass);
SELECT setval(:'qseq'::regclass, (SELECT COALESCE(MAX(id), 0) + 1 FROM :"schema".:"tbl"), false);
COMMIT;
SQL
            echo "       -> Corrigé : ${SCHEMA}.${TABLE}"
        fi
    done
done

echo ""
if [ "$TOTAL_FOUND" -eq 0 ]; then
    echo "==> Tout est en ordre : aucune séquence manquante."
    exit 0
elif [ "$FIX" -eq 1 ]; then
    echo "==> ${TOTAL_FOUND} table(s) corrigée(s)."
    exit 0
else
    echo "==> ${TOTAL_FOUND} table(s) à problème détectée(s)."
    echo "    Relance avec --fix pour corriger automatiquement :"
    echo "    ./check-sequences-puc-prod.sh --fix"
    exit 1
fi