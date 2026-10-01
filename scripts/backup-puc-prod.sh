#!/usr/bin/env bash
#
# Sauvegarde de la base PostgreSQL et des fichiers utilisateurs de la PDC,
# avec rotation automatique (ne garde que les N sauvegardes les plus récentes).
#
# Usage : ./backup-puc-prod.sh [nombre_a_conserver]
#   nombre_a_conserver : défaut 5
#
# Variable d'environnement optionnelle :
#   OFFSITE_TARGET : destination rclone (ex: "monremote:pdc-backups") pour une
#                    copie hors serveur. Un échec de copie est signalé mais
#                    ne bloque pas le déploiement.
#
# À lancer depuis /srv/puc_prod, ou ajuster PROJECT_DIR ci-dessous.
# Peut être appelé manuellement avant un déploiement, ou via cron pour des
# sauvegardes régulières indépendantes des déploiements.
#
# Produit 3 fichiers par sauvegarde :
#   pdc_<date>.dump          : dump PostgreSQL (format custom)
#   pdc_files_<date>.tar.gz  : fichiers utilisateurs (media, files, données QGIS)
#   pdc_config_<date>.tar.gz : .env*, clés JWT (SECRETS : chmod 600, non versionnés)

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/srv/puc_prod}"
BACKUP_DIR="${PROJECT_DIR}/backups"
KEEP="${1:-5}"
OFFSITE_TARGET="${OFFSITE_TARGET:-}"
DATE_TAG="$(date +%Y-%m-%d_%H%M%S)"

DC="docker compose --env-file ${PROJECT_DIR}/.env --env-file ${PROJECT_DIR}/.env.prod --env-file ${PROJECT_DIR}/.env.local"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
cd "$PROJECT_DIR"

DUMP_FILE="${BACKUP_DIR}/pdc_${DATE_TAG}.dump"
FILES_ARCHIVE="${BACKUP_DIR}/pdc_files_${DATE_TAG}.tar.gz"
CONFIG_ARCHIVE="${BACKUP_DIR}/pdc_config_${DATE_TAG}.tar.gz"

cleanup_partial() {
    rm -f "$DUMP_FILE" "$FILES_ARCHIVE" "$CONFIG_ARCHIVE"
}

echo "==> Dump de la base PostgreSQL..."
if ! $DC exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$DUMP_FILE"; then
    echo "ERREUR : pg_dump a échoué. Fichiers partiels supprimés."
    cleanup_partial
    exit 1
fi

echo "==> Archive des fichiers utilisateurs..."
tar -czf "$FILES_ARCHIVE" symfony/public/media symfony/public/files docker/qgis/data

echo "==> Archive de la configuration (.env*, clés JWT)..."
# .env.local (MAILER_DSN, etc.) n'est pas versionné : sans cette archive, il est
# irrécupérable en cas de perte du serveur.
CONFIG_ITEMS=()
for item in .env .env.prod .env.local symfony/config/jwt; do
    if [ -e "$item" ]; then
        CONFIG_ITEMS+=("$item")
    fi
done
if [ "${#CONFIG_ITEMS[@]}" -gt 0 ]; then
    tar -czf "$CONFIG_ARCHIVE" "${CONFIG_ITEMS[@]}"
else
    echo "ATTENTION : aucun fichier de configuration trouvé à archiver."
fi

echo "==> Vérification des sauvegardes..."
if [ ! -s "$DUMP_FILE" ]; then
    echo "ERREUR : le dump est vide. Sauvegarde annulée, fichiers partiels supprimés."
    cleanup_partial
    exit 1
fi

# pg_restore est exécuté dans le conteneur postgres (aucune dépendance côté hôte).
if ! $DC exec -T postgres pg_restore -l < "$DUMP_FILE" > /dev/null 2>&1; then
    echo "ERREUR : le dump ne peut pas être lu par pg_restore. Vérifie manuellement."
    exit 1
fi

tar -tzf "$FILES_ARCHIVE" > /dev/null 2>&1 || {
    echo "ERREUR : l'archive de fichiers est corrompue."
    exit 1
}

if [ -f "$CONFIG_ARCHIVE" ]; then
    tar -tzf "$CONFIG_ARCHIVE" > /dev/null 2>&1 || {
        echo "ERREUR : l'archive de configuration est corrompue."
        exit 1
    }
fi

for f in "$DUMP_FILE" "$FILES_ARCHIVE" "$CONFIG_ARCHIVE"; do
    [ -f "$f" ] && chmod 600 "$f"
done

echo "==> Sauvegarde réussie :"
ls -lh "$DUMP_FILE" "$FILES_ARCHIVE"
[ -f "$CONFIG_ARCHIVE" ] && ls -lh "$CONFIG_ARCHIVE"

if [ -n "$OFFSITE_TARGET" ]; then
    echo "==> Copie hors serveur vers ${OFFSITE_TARGET}..."
    if command -v rclone > /dev/null; then
        for f in "$DUMP_FILE" "$FILES_ARCHIVE" "$CONFIG_ARCHIVE"; do
            [ -f "$f" ] || continue
            rclone copy "$f" "$OFFSITE_TARGET" \
                || echo "ATTENTION : la copie hors serveur de $(basename "$f") a échoué (non bloquant)."
        done
    else
        echo "ATTENTION : rclone n'est pas installé, copie hors serveur ignorée."
    fi
else
    echo "NOTE : aucune copie hors serveur configurée (OFFSITE_TARGET vide)."
    echo "       Les sauvegardes sont sur le même disque que la production."
fi

echo "==> Rotation : conservation des ${KEEP} sauvegardes les plus récentes..."
rotate() {
    local pattern="$1"
    # shellcheck disable=SC2086
    ls -1t "${BACKUP_DIR}"/${pattern} 2>/dev/null | tail -n +$((KEEP + 1)) | while read -r old; do
        echo "   Suppression : $old"
        rm -f "$old"
    done || true
}
rotate 'pdc_*.dump'
rotate 'pdc_files_*.tar.gz'
rotate 'pdc_config_*.tar.gz'

echo "==> Espace disque restant :"
df -h "$BACKUP_DIR"

echo "==> Terminé. $(ls -1 "${BACKUP_DIR}"/pdc_*.dump 2>/dev/null | wc -l) sauvegarde(s) conservée(s)."