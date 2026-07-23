# Spécification MLD — Génération des migrations Laravel (Allo Delivery)

> **But de ce fichier** : servir de spécification à un agent (ex : opencode) pour générer
> automatiquement **toutes les migrations Laravel** du projet Allo Delivery.
> Base : MCD/MLD du projet + cahier des charges V5.1.

## Instructions pour l'agent

- Générer **une migration par table**, dans l'**ordre exact** listé ci-dessous (les tables parentes avant les tables enfants, à cause des clés étrangères).
- Utiliser la syntaxe Laravel moderne (classe anonyme `return new class extends Migration`).
- Pour chaque clé étrangère, utiliser `foreignId(...)->constrained(...)` avec la règle de suppression indiquée (`cascadeOnDelete` ou `nullOnDelete`).
- Ajouter `$table->timestamps()` (created_at + updated_at) sur **toutes** les tables.
- Ajouter `$table->id()` comme clé primaire sur **toutes** les tables.
- Respecter `nullable`, `unique`, `default` et `index` tels que spécifiés.
- Les montants doivent être en `decimal(10, 2)` (jamais `float`).
- Les noms de tables sont au **pluriel**, en snake_case.
- La table `users` **existe déjà** : générer une migration de type `--table=users` qui **ajoute** les colonnes `role` et `phone` (ne pas recréer la table).

## Convention des types

| Besoin | Type Laravel |
|--------|--------------|
| Clé primaire | `id()` |
| Clé étrangère | `foreignId('x_id')->constrained()` |
| Texte court | `string('x')` |
| Texte long | `text('x')` |
| Nombre décimal / argent | `decimal('x', 10, 2)` |
| Coordonnée GPS | `decimal('x', 10, 7)` |
| Booléen | `boolean('x')` |
| Petit entier positif | `unsignedTinyInteger('x')` |
| Date + heure | `timestamp('x')` |
| Liste de valeurs | `enum('x', [...])` |
| Données structurées | `json('x')` |

---

## Ordre de création des tables

1. users (modification)
2. driver_profiles
3. services
4. delivery_zones
5. ai_request_drafts
6. delivery_requests
7. request_status_histories
8. chat_messages
9. delivery_proofs
10. incidents
11. reviews
12. notifications
13. gps_locations *(bonus)*
14. payment_transactions *(bonus optionnel)*

---

## 1. users *(MODIFICATION — table existante)*

Migration `--table=users`. Ajouter ces colonnes :

| Colonne | Type | Contraintes |
|---------|------|-------------|
| role | enum('client', 'driver') | default 'client' |
| phone | string | nullable |

---

## 2. driver_profiles

Profil professionnel d'un livreur. Appartient à un `users`.

| Colonne | Type | Contraintes |
|---------|------|-------------|
| user_id | foreignId → users | constrained, cascadeOnDelete |
| brand_name | string | requis |
| slug | string | **unique** |
| logo_path | string | nullable |
| city | string | nullable |
| rib | string | nullable |
| is_available | boolean | default true |

---

## 3. services

Catalogue des services d'un livreur.

| Colonne | Type | Contraintes |
|---------|------|-------------|
| user_id | foreignId → users | constrained, cascadeOnDelete |
| name | string | requis |
| description | text | nullable |
| base_price | decimal(10,2) | nullable |
| is_active | boolean | default true |

---

## 4. delivery_zones

Trajets / zones de livraison d'un livreur.

| Colonne | Type | Contraintes |
|---------|------|-------------|
| user_id | foreignId → users | constrained, cascadeOnDelete |
| origin_zone | string | requis |
| destination_zone | string | requis |
| fixed_price | decimal(10,2) | nullable |
| is_active | boolean | default true |

---

## 5. ai_request_drafts

Brouillon généré par l'IA à partir du message libre du client (P1).

| Colonne | Type | Contraintes |
|---------|------|-------------|
| user_id | foreignId → users | nullable, constrained, nullOnDelete |
| service_id | foreignId → services | nullable, constrained, nullOnDelete |
| input_message | text | requis |
| generated_data | json | nullable |
| status | enum('pending', 'done', 'failed') | default 'pending' |
| error_message | text | nullable |
| validated_at | timestamp | nullable |

---

## 6. delivery_requests *(TABLE CENTRALE)*

| Colonne | Type | Contraintes |
|---------|------|-------------|
| tracking_number | string | **unique** |
| private_token | string | **unique** |
| client_id | foreignId → users | constrained('users'), cascadeOnDelete |
| driver_id | foreignId → users | constrained('users'), cascadeOnDelete |
| service_id | foreignId → services | nullable, constrained, nullOnDelete |
| delivery_zone_id | foreignId → delivery_zones | nullable, constrained, nullOnDelete |
| ai_request_draft_id | foreignId → ai_request_drafts | nullable, constrained, nullOnDelete |
| recipient_name | string | requis |
| recipient_phone | string | requis |
| pickup_address | string | requis |
| delivery_address | string | requis |
| package_description | text | nullable |
| product_amount | decimal(10,2) | nullable |
| amount_to_collect | decimal(10,2) | nullable |
| proposed_price | decimal(10,2) | nullable |
| confirmation_code_hash | string | nullable (code stocké HACHÉ) |
| scheduled_at | timestamp | nullable |
| picked_up_at | timestamp | nullable |
| delivered_at | timestamp | nullable |
| status | enum (voir ci-dessous) | default 'en_attente' |

**Valeurs de l'enum `status`** :
`en_attente`, `prix_propose`, `confirmee`, `colis_recupere`, `en_livraison`, `livree`, `refusee`, `echec`, `annulee`

**Index supplémentaire** : `index('status')`

---

## 7. request_status_histories

Historique des changements de statut (RG07).

| Colonne | Type | Contraintes |
|---------|------|-------------|
| delivery_request_id | foreignId → delivery_requests | constrained, cascadeOnDelete |
| changed_by | foreignId → users | nullable, constrained('users'), nullOnDelete |
| old_status | string | nullable |
| new_status | string | requis |
| comment | text | nullable |

---

## 8. chat_messages

Messages privés liés à une demande (P1).

| Colonne | Type | Contraintes |
|---------|------|-------------|
| delivery_request_id | foreignId → delivery_requests | constrained, cascadeOnDelete |
| sender_id | foreignId → users | constrained('users'), cascadeOnDelete |
| message_type | string | default 'text' |
| content | text | requis |
| is_read | boolean | default false |

---

## 9. delivery_proofs

Preuves de livraison : photo, ticket, signature (P1).

| Colonne | Type | Contraintes |
|---------|------|-------------|
| delivery_request_id | foreignId → delivery_requests | constrained, cascadeOnDelete |
| uploaded_by | foreignId → users | nullable, constrained('users'), nullOnDelete |
| proof_type | string | requis (photo, ticket, signature) |
| file_path | string | requis |
| receiver_name | string | nullable |

---

## 10. incidents

Incident signalé pendant une livraison (P1).

| Colonne | Type | Contraintes |
|---------|------|-------------|
| delivery_request_id | foreignId → delivery_requests | constrained, cascadeOnDelete |
| reported_by | foreignId → users | nullable, constrained('users'), nullOnDelete |
| type | string | requis |
| description | text | nullable |
| status | string | default 'open' |

---

## 11. reviews

Avis unique du client sur une demande livrée (RG17 : un seul avis par demande).

| Colonne | Type | Contraintes |
|---------|------|-------------|
| delivery_request_id | foreignId → delivery_requests | **unique**, constrained, cascadeOnDelete |
| user_id | foreignId → users | constrained, cascadeOnDelete |
| rating | unsignedTinyInteger | requis (1 à 5) |
| comment | text | nullable |

---

## 12. notifications

Notifications internes de l'application (MVP). Table métier distincte de la table native Laravel.

| Colonne | Type | Contraintes |
|---------|------|-------------|
| user_id | foreignId → users | constrained, cascadeOnDelete |
| delivery_request_id | foreignId → delivery_requests | nullable, constrained, nullOnDelete |
| type | string | requis |
| title | string | requis |
| body | text | nullable |
| read_at | timestamp | nullable (null = non lue) |

---

## 13. gps_locations *(BONUS P2)*

Positions GPS pendant une mission active.

| Colonne | Type | Contraintes |
|---------|------|-------------|
| delivery_request_id | foreignId → delivery_requests | constrained, cascadeOnDelete |
| latitude | decimal(10,7) | requis |
| longitude | decimal(10,7) | requis |
| recorded_at | timestamp | nullable |

---

## 14. payment_transactions *(BONUS P2 — OPTIONNEL)*

Transaction Stripe / PayPal en mode Sandbox. Aucune donnée bancaire réelle (RG16).

| Colonne | Type | Contraintes |
|---------|------|-------------|
| delivery_request_id | foreignId → delivery_requests | constrained, cascadeOnDelete |
| provider | string | requis (stripe, paypal) |
| reference | string | nullable |
| amount | decimal(10,2) | nullable |
| currency | string(3) | default 'MAD' |
| status | string | default 'pending' |
| environment | string | default 'sandbox' |

---

## Règles de gestion à respecter (rappel)

- **RG04** : `tracking_number` et `private_token` sont uniques.
- **RG06** : le code de confirmation est stocké **haché** (`confirmation_code_hash`), jamais en clair.
- **RG07** : chaque changement de statut est historisé (table `request_status_histories`).
- **RG09** : les montants ne peuvent pas être négatifs (à contrôler côté validation/modèle, pas dans la migration).
- **RG16** : aucune donnée bancaire sensible n'est stockée.
- **RG17** : un seul avis par demande (contrainte `unique` sur `delivery_request_id` dans `reviews`).

---

## Vérification finale attendue

Après génération, les commandes suivantes doivent fonctionner sans erreur :

```bash
php artisan migrate
php artisan migrate:status
php artisan migrate:fresh   # recrée toute la base proprement
```