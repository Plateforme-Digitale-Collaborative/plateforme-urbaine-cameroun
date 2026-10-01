#!/usr/bin/env bash
#
# Déploiement complet et automatisé de la PDC en production.
# Conçu pour être appelé sans intervention humaine (déclenché depuis GitHub
# Actions via SSH), avec vérifications automatiques et rollback automatique
# en cas d'échec.
#
# Usage : ./scripts/deploy.sh <branche> [--run-osm-import] [--force]
#
#   <branche>          : branche git à déployer (ex: geosm, main)
#   --run-osm-import   : optionnel, relance aussi `make create-osm-db`
#                        (téléchargement + import OSM, plusieurs minutes,
#                        rarement nécessaire à chaque déploiement)
#   --force            : optionnel, déploie même si aucun nouveau commit
#                        (ex: changement de .env.local, rebuild forcé)
#
# Doit être exécuté depuis le serveur (/srv/puc_prod).
# Log complet écrit dans logs/deploy_<date>.log à la racine du dépôt.
#
# Principe de gestion des échecs :
#   - échec AVANT la bascule (étape 8) : l'ancienne version tourne encore, on
#     remet simplement le code et les tags d'images dans leur état d'origine ;
#   - échec APRÈS la bascule : rollback automatique (code + image).
#   La fonction fail() choisit toute seule le bon comportement.

set -Eeuo pipefail

# Ne pas mourir si la session SSH (GitHub Actions) se coupe en plein déploiement.
trap '' HUP

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

USAGE="Usage : ./scripts/deploy.sh <branche> [--run-osm-import] [--force]"
BRANCH="${1:?$USAGE}"
shift

RUN_OSM_IMPORT=0
FORCE=0
for arg in "$@"; do
    case "$arg" in
        --run-osm-import) RUN_OSM_IMPORT=1 ;;
        --force)          FORCE=1 ;;
        *) echo "Argument inconnu : ${arg}"; echo "$USAGE"; exit 2 ;;
    esac
done

if ! [[ "$BRANCH" =~ ^[A-Za-z0-9._/-]+$ ]]; then
    echo "Nom de branche invalide : ${BRANCH}"
    exit 2
fi

SITE_URL="https://plateforme-urbaine.cm/"
DC="docker compose --env-file .env --env-file .env.prod --env-file .env.local"

LOG_DIR="${PROJECT_DIR}/logs"
mkdir -p "$LOG_DIR"
LOG_FILE="${LOG_DIR}/deploy_$(date +%Y-%m-%d_%H%M%S).log"
# tee -p : continue d'écrire dans le fichier même si la sortie (SSH) est fermée.
exec > >(tee -a -p "$LOG_FILE") 2>&1

echo "========================================================"
echo "Déploiement PDC — $(date)"
echo "Branche cible : ${BRANCH}"
echo "========================================================"

# Verrou : un seul déploiement à la fois.
exec 9>"${LOG_DIR}/.deploy.lock"
if ! flock -n 9; then
    echo "!!! Un autre déploiement est déjà en cours. Abandon."
    exit 1
fi

# --- État suivi pour permettre un retour arrière propre --------------------
PREVIOUS_COMMIT=""
CODE_CHANGED=0
PREVIOUS_IMAGE_SAVED=0
CURRENT_QGIS_ID=""
MIGRATED=0
SWITCHED=0

restore_code() {
    if [ "$CODE_CHANGED" -eq 1 ] && [ -n "$PREVIOUS_COMMIT" ]; then
        echo "Retour au commit : ${PREVIOUS_COMMIT}"
        git checkout --quiet "$PREVIOUS_COMMIT" \
            || echo "ATTENTION : impossible de checkout ${PREVIOUS_COMMIT} automatiquement."
    fi
}

restore_images() {
    local restored=1
    if [ "$PREVIOUS_IMAGE_SAVED" -eq 1 ] && docker image inspect puc-frankenphp:previous > /dev/null 2>&1; then
        docker tag puc-frankenphp:previous puc-frankenphp:latest && restored=0
    fi
    if [ -n "$CURRENT_QGIS_ID" ]; then
        docker tag "$CURRENT_QGIS_ID" puc-qgis:latest 2>/dev/null \
            || echo "ATTENTION : impossible de remettre l'ancien tag de l'image puc-qgis."
    fi
    return $restored
}

rollback() {
    echo ""
    echo "########################################################"
    echo "### ROLLBACK AUTOMATIQUE EN COURS"
    echo "########################################################"
    restore_code

    if restore_images; then
        echo "Restauration de l'image frankenphp précédente..."
        $DC up -d --no-build frankenphp \
            || echo "ATTENTION : le redémarrage de frankenphp sur l'ancienne image a échoué."
    else
        echo "ATTENTION : aucune image de rollback disponible (puc-frankenphp:previous absente)."
    fi

    echo "Vérification du site après rollback..."
    sleep 10
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$SITE_URL" || true)"
    echo "Code HTTP après rollback : ${code}"

    echo ""
    echo "### Rollback terminé. Vérifie manuellement l'état du site :"
    echo "###   curl -sI ${SITE_URL}"
    echo "### Le dump de sauvegarde pré-déploiement est disponible dans ${PROJECT_DIR}/backups/"
    echo "### Rappel (runbook §8) : ne restaure ce dump qu'en tout dernier recours —"
    echo "### une base déjà migrée reste généralement compatible avec l'ancien code"
    echo "### (vrai tant que les migrations sont additives : voir migrations destructrices)."
}

fail() {
    set +e
    trap - ERR
    echo ""
    echo "!!! ÉCHEC : $1"

    if [ "$SWITCHED" -eq 1 ]; then
        rollback
    else
        restore_code
        restore_images || true
        echo "!!! Déploiement interrompu avant la bascule : l'ancienne version continue de tourner."
        if [ "$MIGRATED" -eq 1 ]; then
            echo "!!! ATTENTION : la migration Doctrine a DÉJÀ été appliquée à la base."
            echo "!!! Vérifie que l'ancien code reste compatible avec le nouveau schéma."
        fi
    fi
    echo "!!! Voir ${LOG_FILE} pour le détail."
    exit 1
}

# Toute erreur non prévue passe par fail() (donc rollback si la bascule a eu lieu).
trap 'fail "erreur inattendue (ligne ${LINENO})"' ERR

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 1/9 — Préchecks"
# ---------------------------------------------------------------------------
for f in .env .env.prod .env.local; do
    [ -f "$f" ] || fail "fichier de configuration manquant : ${f}"
done
command -v flock > /dev/null || fail "flock est introuvable"
echo "OK."

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 2/9 — Récupération du code distant et détection des changements"
# ---------------------------------------------------------------------------
PREVIOUS_COMMIT="$(git rev-parse HEAD)"
echo "Commit actuel (point de rollback) : ${PREVIOUS_COMMIT}"

git fetch origin || fail "git fetch a échoué"
git rev-parse --verify --quiet "origin/${BRANCH}^{commit}" > /dev/null \
    || fail "la branche origin/${BRANCH} n'existe pas"

TARGET_COMMIT="$(git rev-parse "origin/${BRANCH}")"
echo "Commit cible : ${TARGET_COMMIT}"

# Fait AVANT la sauvegarde : un run « à vide » ne doit pas consommer un emplacement
# de rotation des sauvegardes.
if [ "$TARGET_COMMIT" == "$PREVIOUS_COMMIT" ] && [ "$FORCE" -eq 0 ]; then
    echo "Aucun changement détecté sur ${BRANCH}. Déploiement annulé (rien à faire)."
    echo "(Utilise --force pour déployer quand même.)"
    exit 0
fi

# Vérification du volume des clés JWT, sur la version de compose.prod.yaml qui
# va être déployée, AVANT toute modification (runbook § Pièges connus A).
# init-jwt-keypair (Étape 8) tourne avec --overwrite : sans volume persistant
# pour config/jwt, TOUS les utilisateurs actifs sont déconnectés à chaque
# déploiement. Heuristique : cherche un montage dont la cible est config/jwt.
if ! git show "origin/${BRANCH}:compose.prod.yaml" | grep -Eq \
        -e '^[[:space:]]*-[[:space:]]+[^#[:space:]]+:[^#[:space:]]*config/jwt' \
        -e '^[[:space:]]*target:[[:space:]]*[^#]*config/jwt'; then
    fail "le volume des clés JWT ne semble pas monté dans compose.prod.yaml (voir runbook § Pièges connus A). Ajoute le montage (ex: ./symfony/config/jwt:/app/config/jwt), copie les clés existantes du conteneur vers l'hôte, puis relance le déploiement."
fi
echo "Volume JWT trouvé dans compose.prod.yaml — OK."

if ! compgen -G "symfony/config/jwt/*.pem" > /dev/null; then
    echo "ATTENTION : aucune clé .pem dans symfony/config/jwt sur l'hôte."
    echo "           init-jwt-keypair va en générer de nouvelles (utilisateurs déconnectés une fois)."
fi

# Log du diff infra pour traçabilité, sans bloquer le déploiement
# (le runbook §3 demande de LIRE ce diff avant de continuer ; en mode
# automatisé sans humain dans la boucle, on ne peut pas juger de l'impact
# d'un changement de volumes/variables/services, donc ceci reste
# non-bloquant par design — mais garde un œil sur ce log après coup).
echo "--- Diff infrastructure (à titre informatif, NON BLOQUANT) ---"
git diff "HEAD" "origin/${BRANCH}" -- compose.yaml compose.prod.yaml Makefile docker symfony/Dockerfile vue/Dockerfile docs/Dockerfile || true
echo "--- Fin du diff ---"

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 3/9 — Sauvegarde"
# ---------------------------------------------------------------------------
bash "${PROJECT_DIR}/scripts/backup-puc-prod.sh" 5 || fail "la sauvegarde a échoué"

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 4/9 — Bascule du code et image de rollback"
# ---------------------------------------------------------------------------
CURRENT_FRANKENPHP_ID="$(docker image inspect puc-frankenphp:latest --format '{{.Id}}' 2>/dev/null || true)"
if [ -n "$CURRENT_FRANKENPHP_ID" ]; then
    docker tag "$CURRENT_FRANKENPHP_ID" puc-frankenphp:previous
    PREVIOUS_IMAGE_SAVED=1
    echo "Image actuelle taguée comme puc-frankenphp:previous (${CURRENT_FRANKENPHP_ID})"
else
    echo "ATTENTION : aucune image puc-frankenphp:latest existante — pas de point de rollback image."
fi

CURRENT_QGIS_ID="$(docker image inspect puc-qgis:latest --format '{{.Id}}' 2>/dev/null || true)"

CODE_CHANGED=1
git checkout "$BRANCH" || fail "git checkout ${BRANCH} a échoué"
git pull --ff-only origin "$BRANCH" || fail "git pull --ff-only a échoué (historique divergent ?)"

NEW_COMMIT="$(git rev-parse HEAD)"
echo "Nouveau commit : ${NEW_COMMIT}"

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 5/9 — Construction des images"
# ---------------------------------------------------------------------------
# Rien n'est encore basculé : en cas d'échec, l'ancienne version continue de
# tourner, inutile de recréer le conteneur.
make build || fail "make build a échoué"

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 6/9 — Migration (tout-ou-rien)"
# ---------------------------------------------------------------------------
# -T : pas de pseudo-TTY (exécution non interactive via SSH).
$DC run --rm --no-deps -T frankenphp php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing \
    || fail "migration Doctrine (annulée automatiquement par --all-or-nothing)"
MIGRATED=1

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 7/9 — Vérification et correction des séquences d'identifiants"
# ---------------------------------------------------------------------------
# L'ancien code tourne encore (bascule à l'Étape 8) : fail() remet donc le code
# dans son état d'origine sans toucher aux conteneurs.
bash "${PROJECT_DIR}/scripts/check-sequences-puc-prod.sh" --fix || fail "correction des séquences d'id"

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 8/9 — Bascule sur le nouveau code"
# ---------------------------------------------------------------------------
SWITCHED=1
SWITCH_TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

make up || fail "make up a échoué"

NEW_QGIS_ID="$(docker image inspect puc-qgis:latest --format '{{.Id}}' 2>/dev/null || true)"
if [ "$CURRENT_QGIS_ID" != "$NEW_QGIS_ID" ]; then
    echo "ATTENTION : l'image qgis a changé (${CURRENT_QGIS_ID} -> ${NEW_QGIS_ID}). Non bloquant, mais à vérifier."
fi

make update-database || fail "make update-database a échoué"
make init-jwt-keypair || fail "make init-jwt-keypair a échoué"

if [ "$RUN_OSM_IMPORT" -eq 1 ]; then
    echo "Import OSM demandé explicitement..."
    make create-osm-db || echo "ATTENTION : l'import OSM a échoué, non bloquant pour le reste du déploiement."
fi

make cc || fail "make cc a échoué"

# ---------------------------------------------------------------------------
echo ""
echo "### Étape 9/9 — Vérifications de santé"
# ---------------------------------------------------------------------------
FRANKENPHP_CID="$($DC ps -q frankenphp | head -1 || true)"
HTTP_CODE="000"
HEALTH_STATUS="unknown"
MAX_ATTEMPTS=30

echo "Attente que le site réponde (jusqu'à $((MAX_ATTEMPTS * 3)) s)..."
for attempt in $(seq 1 "$MAX_ATTEMPTS"); do
    HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$SITE_URL" || true)"
    if [ -n "$FRANKENPHP_CID" ]; then
        HEALTH_STATUS="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$FRANKENPHP_CID" 2>/dev/null || echo unknown)"
    fi
    echo "  tentative ${attempt}/${MAX_ATTEMPTS} : HTTP ${HTTP_CODE}, santé ${HEALTH_STATUS}"

    if [ "$HTTP_CODE" = "200" ]; then
        case "$HEALTH_STATUS" in healthy|none|unknown) break ;; esac
    fi
    sleep 3
done

echo "Code HTTP du site : ${HTTP_CODE}"
echo "Statut de santé du conteneur : ${HEALTH_STATUS}"

[ "$HTTP_CODE" = "200" ] || fail "le site ne répond pas correctement (code ${HTTP_CODE})"

case "$HEALTH_STATUS" in
    healthy|none|unknown) ;;
    *) fail "le conteneur frankenphp n'est pas sain (statut : ${HEALTH_STATUS})" ;;
esac

# Vérification (runbook §7) : la base est à jour et Doctrine peut lire son état,
# sans erreur de connexion ni exception.
echo "Vérification de l'état des migrations Doctrine..."
if ! MIGRATION_STATUS="$($DC exec -T frankenphp php bin/console doctrine:migrations:up-to-date --no-interaction 2>&1)"; then
    echo "$MIGRATION_STATUS"
    fail "la base n'est pas à jour ou la vérification des migrations a échoué"
fi
echo "$MIGRATION_STATUS"
echo "Version courante : $($DC exec -T frankenphp php bin/console doctrine:migrations:current --no-interaction 2>&1 || true)"

# Uniquement les logs postérieurs à la bascule (pas ceux d'un ancien conteneur).
RECENT_ERRORS="$($DC logs --no-color --since "$SWITCH_TS" frankenphp 2>/dev/null | grep -iE "JWTEncodeFailure|NotNullConstraintViolationException" | grep -v acme_client || true)"
if [ -n "$RECENT_ERRORS" ]; then
    echo "Des erreurs critiques connues ont été détectées dans les logs depuis la bascule :"
    echo "$RECENT_ERRORS"
    fail "erreurs critiques détectées dans les logs"
fi

echo ""
echo "========================================================"
echo "DÉPLOIEMENT RÉUSSI"
echo "Commit déployé : ${NEW_COMMIT}"
echo "Site : ${SITE_URL} (HTTP ${HTTP_CODE})"
echo "========================================================"
echo ""
echo "Rappel : ce script ne teste pas le parcours utilisateur complet"
echo "(inscription, connexion réelle, réception d'e-mail). Fais-le"
echo "manuellement après un déploiement qui touche l'authentification,"
echo "les comptes, ou l'envoi d'e-mails (voir runbook §7 et Piège B —"
echo "MAILER_DSN dans .env.local n'est ni versionné ni vérifiable"
echo "automatiquement)."