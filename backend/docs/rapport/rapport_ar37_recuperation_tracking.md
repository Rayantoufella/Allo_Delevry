# Rapport — AR-37 : Récupération, ticket (preuve) et tracking privé

**Date :** 6 août 2026 (mise à jour après audit fonctionnel)
**Périmètre :** `D:\AlloDelevry\backend`
**Référence :** Cahier des charges — F11 (lien privé de suivi), F14 (preuves et code sécurisé)
**Statut :** Terminé (implémentation + tests) — branche `feature/PDF`

---

## 1. Contexte

AR-37 couvre trois volets : la **récupération** (preuves — déjà livré en AR-38), le **ticket** et le
**tracking privé**. Un audit du cahier des charges (F14) a confirmé que le « ticket » est un
**type de preuve** (photo, ticket ou signature) et non un document PDF : l'initiative « ticket PDF via
Blade » a donc été **annulée** (fausse initiative), et le tracking privé a été complété selon F11.

## 2. Décisions prises (avec l'utilisateur)

| # | Décision | Impact |
|---|----------|--------|
| 1 | Le frontend Vue est séparé → **aucun template Blade** côté backend | Suppression du PDF dompdf (route, méthode, vue, tests) |
| 2 | Le « ticket » du cahier des charges = **preuve de type ticket** (F14) | Déjà implémenté et testé (AR-38) — rien à faire |
| 3 | **GPS et e-mail = bonus** (implémentés par l'utilisateur à la fin du projet) | Position GPS exclue du tracking |
| 4 | F12 Reverb sera traité sur une **branche dédiée `feature/Reverb`** | Pas de diffusion temps réel ici |
| 5 | Tracking privé (F11) : **chat, preuves et infos livreur ajoutés** | `PublicTrackingResource` enrichi |

## 3. Actions réalisées sur `feature/PDF`

### 3.1 Annulation du ticket PDF (fausse initiative)

- `routes/api.php` : route `GET /delivery-requests/{id}/ticket` supprimée.
- `DeliveryRequestController` : méthode `ticket()` + import `Barryvdh\DomPDF\Facade\Pdf` supprimés.
- `resources/views/tickets/ticket.blade.php` et `tests/Feature/DeliveryRequestTicketTest.php` supprimés.
- dompdf reste installé (ajouté en AR-04) — aucune dépendance retirée, aucun coût.

### 3.2 Tracking privé complété (F11)

`GET /api/tracking/{privateToken}` (public, throttle:60,1) renvoie désormais :

| Clé | Contenu |
|-----|---------|
| `tracking_number`, `status`, dates | Identité et statut courant |
| `pickup_address`, `delivery_address`, `recipient_name` | Adresses et destinataire |
| `client` (nom, téléphone) | Expéditeur (informations utiles) |
| `driver` (nom, téléphone, `brand_name`) | Livreur + marque (profil) |
| `service` (nom), `delivery_zone` (origine → destination) | Prestations |
| `timeline` | Historique des statuts (statuts précédent/nouveau, commentaire, date) |
| `chat_messages` | 20 derniers échanges (nom de l'expéditeur, contenu, date) |
| `proofs` | Preuves (type photo/ticket/signature, URL du fichier, nom du receveur) |

- Eager-loads ajoutés dans `tracking()` : `client`, `driver.driverProfile`, `service`, `deliveryZone`,
  `chatMessages.sender`, `proofs`.
- Aucune donnée sensible : pas de `private_token`, ni de `confirmation_code_hash` (testé).

### 3.3 Tests

`tests/Feature/TrackingTest.php` (3 tests, 21 assertions) :

1. Suivi complet : toutes les clés F11 présentes (participants, chat, preuves, timeline, zone).
2. Jeton privé inconnu → **404**.
3. Pas de fuite de données sensibles (`private_token`, `confirmation_code_hash` absents).

## 4. Vérifications

- Pint propre ; suite complète **43 tests passed (118 assertions)**.
- Route `/api/tracking/{privateToken}` inchangée (aucune rupture pour le frontend existant).

## 5. Références

- Branche : `feature/PDF` → PR : https://github.com/Rayantoufella/Allo_Delevry/pull/new/feature/PDF
- Suite : branche `feature/Reverb` (F12 temps réel) — créée séparément.
