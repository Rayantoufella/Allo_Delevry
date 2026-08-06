# Guide — 08 : Uploads et preuves de livraison

**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** cahier des charges — F14 (photo/…, preuves), AR-30 (queues/uploads)

## Vue d'ensemble

Le livreur (ou le client participant) dépose une **preuve de livraison** liée à une demande : un fichier image (`storage/app/public/proofs/`) + des métadonnées (type de preuve, nom du destinataire). L'API reçoit un **vrai fichier multipart** (`file`), le valide (image jpg/jpeg/png/webp ≤ 2 Mo), l'écrit sur le disque `public` et stocke le chemin relatif en base. Les fichiers sont servis via le lien symbolique `storage` (`asset('storage/...')`). L'upload est synchrone ; seul le **traitement des notifications** passe par la queue (guide 05). Les corrections AR-05 (B4) ont verrouillé la propriété : `uploaded_by` est posé côté serveur.

## Fichiers et rôles (exhaustif)

| Fichier | Rôle | Points clés |
|---------|------|-------------|
| `app/Http/Controllers/Api/DeliveryProofController.php` | CRUD des preuves | `index()` filtre par `delivery_request_id` (en vérifiant l'appartenance via `authorize('view', $deliveryRequest)`) ou par participation ; `store()` : `authorize('create', [DeliveryProof::class, $deliveryRequest])` + `$file->store('proofs', 'public')` + `uploaded_by = user()->id` ; `update()` : **supprime l'ancien fichier** si remplacé (`Storage::disk('public')->delete(...)`) ; `destroy()` : suppression autorisée |
| `app/Http/Requests/StoreDeliveryProofRequest.php` | Validation de création | `delivery_request_id` (exists), `proof_type` (max:50), **`file` : required, image, mimes:jpg,jpeg,png,webp, max:2048**, `receiver_name` nullable |
| `app/Http/Requests/UpdateDeliveryProofRequest.php` | Validation de mise à jour | Champs `somtims` ; le fichier est remplaçable |
| `app/Http/Resources/DeliveryProofResource.php` | Formatage de la réponse | `file_url` = `asset('storage/'.$file_path)` — l'URL publique complète ; masque le chemin réel |
| `app/Models/DeliveryProof.php` | Modèle | `belongsTo(DeliveryRequest)` ; fillable : delivery_request_id, uploaded_by, proof_type, file_path, receiver_name |
| `app/Models/DeliveryRequest.php` | Relation | `hasMany(DeliveryProof::class)` — eager-loaded dans `tracking()` (guide 03) |
| `app/Policies/DeliveryProofPolicy.php` | Autorisation | create : participants ; + `uploaded_by` forcé (B4) |
| `config/filesystems.php` | Disques | Disque `public` (root `storage/app/public`) — les fichiers `.gitignore` de la base `public/storage` |
| `docker/php/Dockerfile` | Extension d'image PHP | Ajout de `libjpeg62-turbo-dev`/`libfreetype6-dev` + `--with-jpeg --with-freetype` — **obligatoire** : sans JPEG, `imagejpeg` manque et tous les tests d'upload échouent |
| `database/migrations/2026_07_22_141323_create_delivery_proofs_table.php` | Table | `delivery_request_id` (FK), `uploaded_by` (FK users), `proof_type`, `file_path`, `receiver_name` nullable, timestamps |
| `tests/Feature/DeliveryProofUploadTest.php` | Tests d'upload | 7 tests : upload valide 201 + `Storage::assertExists`, type/mime/taille refusés, remplacement (ancien fichier supprimé), suppression, autorisations |
| `config/filesystems.php` (lien public) | Exposition | `public` disk → `storage:link` déjà exécuté : `public/storage` pointe vers `storage/app/public` |

## Actions passées (rapports liés)

- **AR-30** — `docs/rapport/rapport_ar30_queues_uploads.md` : uploads réels des preuves (partie 2), Dockerfile GD, tests upload.
- **AR-05 §4.2 / B4** — `docs/rapports/rapport_correction_delivery.md` : `uploaded_by` forcé côté serveur (le client ne peut temporaire pas se faire passer pour livreur).

## Pièges et points d'attention

- **GD doit inclure JPEG** : `--with-jpeg` dans le Dockerfile — après un `docker-compose build app`, sinon tout upload d'image en 500.
- **Le nom du fichier en base est le chemin relatif** (`proofs/xxx.jpg`) ; l'URL exposée vient de `DeliveryProofResource::file_url`.
- **`file_path` n'est pas fourni par le client** : le champ multipart s'appelle `file` (voir `StoreDeliveryProofRequest`).
- **Suppression du fichier physique** sur remplacement/suppression : garder la cohérence disque ←base ; en cas de `delete`, le fichier reste orphelin (pas de purge automatique de ce côté).