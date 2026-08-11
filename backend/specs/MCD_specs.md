# Spécification MCD — Génération des modèles Eloquent (Allo Delivery)

> **But de ce fichier** : servir de spécification à un agent (ex : opencode) pour générer
> tous les **modèles Eloquent** du projet Allo Delivery, avec leurs **relations**,
> `fillable`, `casts` et constantes de statut.
> Base : relations du MCD + migrations déjà créées (voir MLD).

## Instructions pour l'agent

- Générer **un modèle par table** dans `app/Models/`.
- Chaque modèle étend `Illuminate\Database\Eloquent\Model` (sauf `User` qui étend `Authenticatable`).
- Définir la propriété `$fillable` avec les colonnes remplissables (pas `id`, `created_at`, `updated_at`).
- Définir `$casts` pour les booléens, dates, décimaux et JSON.
- Déclarer **les deux côtés** de chaque relation (ex : `User hasMany Service` ET `Service belongsTo User`).
- Utiliser les bons types de relations Laravel : `hasOne`, `hasMany`, `belongsTo`.
- Pour les statuts (enum), créer des **constantes** de classe pour éviter d'écrire le texte en dur.
- Générer les modèles avec : `php artisan make:model NomModele`.

## Résumé des relations (vue d'ensemble)

```
User (client)  ──< delivery_requests (client_id)
User (driver)  ──< delivery_requests (driver_id)
User           ──1 driver_profile
User           ──< services
User           ──< delivery_zones
User           ──< ai_request_drafts
User           ──< notifications

Service        ──< delivery_requests
DeliveryZone   ──< delivery_requests
AiRequestDraft ──< delivery_requests

DeliveryRequest ──< request_status_histories
DeliveryRequest ──< chat_messages
DeliveryRequest ──< delivery_proofs
DeliveryRequest ──< incidents
DeliveryRequest ──1 review
DeliveryRequest ──< notifications
DeliveryRequest ──< gps_locations       (bonus)
DeliveryRequest ──< payment_transactions (bonus)
```

Légende : `──1` = a un(e) seul(e) (hasOne) · `──<` = a plusieurs (hasMany)

---

## 1. User

- **Table** : `users`
- **Étend** : `Authenticatable`
- **fillable** : `name`, `email`, `password`, `role`, `phone`
- **hidden** : `password`, `remember_token`
- **casts** : `email_verified_at` → datetime, `password` → hashed

**Constantes** :
```php
const ROLE_CLIENT = 'client';
const ROLE_DRIVER = 'driver';
```

**Relations** :
- `driverProfile()` → hasOne(DriverProfile)
- `services()` → hasMany(Service)
- `deliveryZones()` → hasMany(DeliveryZone)
- `aiRequestDrafts()` → hasMany(AiRequestDraft)
- `notifications()` → hasMany(Notification)
- `clientRequests()` → hasMany(DeliveryRequest, 'client_id')
- `driverRequests()` → hasMany(DeliveryRequest, 'driver_id')

---

## 2. DriverProfile

- **Table** : `driver_profiles`
- **fillable** : `user_id`, `brand_name`, `slug`, `logo_path`, `city`, `rib`, `is_available`
- **casts** : `is_available` → boolean

**Relations** :
- `user()` → belongsTo(User)

---

## 3. Service

- **Table** : `services`
- **fillable** : `user_id`, `name`, `description`, `base_price`, `is_active`
- **casts** : `base_price` → decimal:2, `is_active` → boolean

**Relations** :
- `user()` → belongsTo(User)  *(le livreur)*
- `deliveryRequests()` → hasMany(DeliveryRequest)

---

## 4. DeliveryZone

- **Table** : `delivery_zones`
- **fillable** : `user_id`, `origin_zone`, `destination_zone`, `fixed_price`, `is_active`
- **casts** : `fixed_price` → decimal:2, `is_active` → boolean

**Relations** :
- `user()` → belongsTo(User)
- `deliveryRequests()` → hasMany(DeliveryRequest)

---

## 5. AiRequestDraft

- **Table** : `ai_request_drafts`
- **fillable** : `user_id`, `service_id`, `input_message`, `generated_data`, `status`, `error_message`, `validated_at`
- **casts** : `generated_data` → array, `validated_at` → datetime

**Constantes** :
```php
const STATUS_PENDING = 'pending';
const STATUS_DONE    = 'done';
const STATUS_FAILED  = 'failed';
```

**Relations** :
- `user()` → belongsTo(User)
- `service()` → belongsTo(Service)
- `deliveryRequests()` → hasMany(DeliveryRequest)

---

## 6. DeliveryRequest *(MODÈLE CENTRAL)*

- **Table** : `delivery_requests`
- **fillable** : `tracking_number`, `private_token`, `client_id`, `driver_id`, `service_id`, `delivery_zone_id`, `ai_request_draft_id`, `recipient_name`, `recipient_phone`, `pickup_address`, `delivery_address`, `package_description`, `product_amount`, `amount_to_collect`, `proposed_price`, `scheduled_at`, `picked_up_at`, `delivered_at`, `status`
- **hidden** : `private_token`
- **casts** : `product_amount` → decimal:2, `amount_to_collect` → decimal:2, `proposed_price` → decimal:2, `scheduled_at` → datetime, `picked_up_at` → datetime, `delivered_at` → datetime

**Constantes de statut** :
```php
const STATUS_EN_ATTENTE     = 'en_attente';
const STATUS_PRIX_PROPOSE   = 'prix_propose';
const STATUS_CONFIRMEE      = 'confirmee';
const STATUS_COLIS_RECUPERE = 'colis_recupere';
const STATUS_EN_LIVRAISON   = 'en_livraison';
const STATUS_LIVREE         = 'livree';
const STATUS_REFUSEE        = 'refusee';
const STATUS_ECHEC          = 'echec';
const STATUS_ANNULEE        = 'annulee';
```

**Relations** :
- `client()` → belongsTo(User, 'client_id')
- `driver()` → belongsTo(User, 'driver_id')
- `service()` → belongsTo(Service)
- `deliveryZone()` → belongsTo(DeliveryZone)
- `aiRequestDraft()` → belongsTo(AiRequestDraft)
- `statusHistories()` → hasMany(RequestStatusHistory)
- `chatMessages()` → hasMany(ChatMessage)
- `proofs()` → hasMany(DeliveryProof)
- `incidents()` → hasMany(Incident)
- `review()` → hasOne(Review)
- `notifications()` → hasMany(Notification)
- `gpsLocations()` → hasMany(GpsLocation)
- `paymentTransactions()` → hasMany(PaymentTransaction)

**Scopes utiles (optionnel)** :
- `scopeActive()` : statuts en cours (ni livree, refusee, echec, annulee)

---

## 7. RequestStatusHistory

- **Table** : `request_status_histories`
- **fillable** : `delivery_request_id`, `changed_by`, `old_status`, `new_status`, `comment`

**Relations** :
- `deliveryRequest()` → belongsTo(DeliveryRequest)
- `changedBy()` → belongsTo(User, 'changed_by')

---

## 8. ChatMessage

- **Table** : `chat_messages`
- **fillable** : `delivery_request_id`, `sender_id`, `message_type`, `content`, `is_read`
- **casts** : `is_read` → boolean

**Relations** :
- `deliveryRequest()` → belongsTo(DeliveryRequest)
- `sender()` → belongsTo(User, 'sender_id')

---

## 9. DeliveryProof

- **Table** : `delivery_proofs`
- **fillable** : `delivery_request_id`, `uploaded_by`, `proof_type`, `file_path`, `receiver_name`

**Relations** :
- `deliveryRequest()` → belongsTo(DeliveryRequest)
- `uploadedBy()` → belongsTo(User, 'uploaded_by')

---

## 10. Incident

- **Table** : `incidents`
- **fillable** : `delivery_request_id`, `reported_by`, `type`, `description`, `status`

**Relations** :
- `deliveryRequest()` → belongsTo(DeliveryRequest)
- `reportedBy()` → belongsTo(User, 'reported_by')

---

## 11. Review

- **Table** : `reviews`
- **fillable** : `delivery_request_id`, `user_id`, `rating`, `comment`
- **casts** : `rating` → integer

**Relations** :
- `deliveryRequest()` → belongsTo(DeliveryRequest)
- `user()` → belongsTo(User)  *(le client auteur)*

---

## 12. Notification

- **Table** : `notifications`
- **fillable** : `user_id`, `delivery_request_id`, `type`, `title`, `body`, `read_at`
- **casts** : `read_at` → datetime

**Relations** :
- `user()` → belongsTo(User)
- `deliveryRequest()` → belongsTo(DeliveryRequest)

---

## 13. GpsLocation *(BONUS)*

- **Table** : `gps_locations`
- **fillable** : `delivery_request_id`, `latitude`, `longitude`, `recorded_at`
- **casts** : `latitude` → decimal:7, `longitude` → decimal:7, `recorded_at` → datetime

**Relations** :
- `deliveryRequest()` → belongsTo(DeliveryRequest)

---

## 14. PaymentTransaction *(BONUS OPTIONNEL)*

- **Table** : `payment_transactions`
- **fillable** : `delivery_request_id`, `provider`, `reference`, `amount`, `currency`, `status`, `environment`
- **casts** : `amount` → decimal:2

**Relations** :
- `deliveryRequest()` → belongsTo(DeliveryRequest)

---

## Factories et Seeders (partie AR-22)

Pour chaque modèle, générer une **factory** (`php artisan make:factory`) produisant des données cohérentes :

- **UserFactory** : générer des users avec `role` = client ou driver.
- **DriverProfileFactory** : `slug` unique (ex : `fake()->unique()->slug()`), `is_available` = true.
- **ServiceFactory** / **DeliveryZoneFactory** : rattacher à un user driver, `is_active` = true.
- **DeliveryRequestFactory** : `tracking_number` et `private_token` uniques, `status` = `en_attente` par défaut.

Créer un **DatabaseSeeder** qui génère un scénario de démonstration complet :
1. Quelques livreurs avec profil, services et zones.
2. Quelques clients.
3. Des demandes de livraison à différents statuts (en_attente, confirmee, en_livraison, livree).
4. Pour les demandes livrées : ajouter une preuve et un avis.

Commande finale de test :
```bash
php artisan migrate:fresh --seed
```

---

## Rappels importants

- Les relations doivent être déclarées **des deux côtés** (belongsTo + hasMany/hasOne).
- Les clés étrangères qui ne suivent pas la convention (`client_id`, `driver_id`, `sender_id`, `changed_by`, etc.) doivent préciser le **nom de la colonne** en 2e argument de la relation.
- `Review` utilise `hasOne` côté `DeliveryRequest` (un seul avis par demande — RG17).
- Ne jamais exposer `private_token` (le mettre dans `$hidden`). Les colonnes `confirmation_code_*` ont été **supprimées** (la génération de code a été retirée, les boutons de statut sont côté livreur).