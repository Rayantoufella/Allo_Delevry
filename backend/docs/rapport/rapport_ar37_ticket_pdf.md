# Rapport — AR-37 : Ticket PDF d'une demande de livraison

**Date :** 6 août 2026
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — AR-37 « Récupération, ticket PDF et tracking privé », F11/F14
**Statut :** Terminé (implémentation + tests) — branche `feature/DashbordDriver`

---

## 1. Contexte

AR-37 couvre la récupération (preuves déjà livrées), le **ticket PDF** et le tracking privé (déjà livré :
`GET /api/tracking/{privateToken}`). Il manquait le **ticket PDF** : un document imprimable de la demande
de livraison pour le livreur et le client. Le paquet `barryvdh/laravel-dompdf` était déjà installé
(ajouté en AR-04) → **aucune dépendance à ajouter**.

## 2. Tableau récapitulatif des actions

| # | Action | Fichier | Résultat |
|---|--------|---------|----------|
| 1 | Méthode `ticket()` | `app/Http/Controllers/Api/DeliveryRequestController.php` | Génère le PDF via `Pdf::loadView('tickets.ticket')->stream('ticket-DLV-….pdf')` |
| 2 | Template | `resources/views/tickets/ticket.blade.php` | Ticket imprimable (CSS tables, compatible dompdf) |
| 3 | Route | `routes/api.php` | `GET /delivery-requests/{deliveryRequest}/ticket` (groupe `auth:sanctum`, **pas** `role:driver` : le client est aussi participant) |
| 4 | Tests | `tests/Feature/DeliveryRequestTicketTest.php` | 5 tests |

## 3. Détail de la méthode

- `$this->authorize('view', $deliveryRequest)` → **participants uniquement** (client ou livreur, policy existante, RG01).
- Eager-loads : `client`, `driver`, `driver.driverProfile` (marque), `service`, `deliveryZone`.
- Libellé du statut en français (même mapping que le job `CreateStatusChangedNotificationJob`).
- Rendu : **affichage en ligne** (`->stream()`, choix utilisateur) — le navigateur affiche le PDF et permet l'impression.

## 4. Contenu du ticket

- En-tête : marque du livreur + badge de statut + date de création.
- Numéro de suivi, statut, dates prévue / livrée.
- Expéditeur (client) → Destinataire (nom + téléphone).
- Adresses de retrait et de livraison.
- Service, zone (origine → destination), description du colis.
- Montants en DH : prix de la course, montant à encaisser, valeur déclarée (2 décimales, format FR).
- Aucune donnée sensible (pas de RIB, pas de token privé).

## 5. Tests

- Livreur → **200** + `Content-Type: application/pdf`.
- Client participant → **200** + `application/pdf`.
- Utilisateur non participant → **403**.
- Non authentifié → **401**.
- Demande inconnue → **404**.

## 6. Vérifications

- Pint propre ; **45 tests passed (106 assertions)** (suite complète).
- Route confirmée : `GET api/delivery-requests/{deliveryRequest}/ticket`.

## 7. Références

- Branche : `feature/DashbordDriver`.
- Suite logique : cet épique AR-6 backend est désormais complet (AR-31/33/34/36/40 vérifiés sur `main`,
  AR-32/35/37/38/39 sur la branche).
