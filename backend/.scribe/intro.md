# Introduction

API de la plateforme Allo Delivery — gestion des livraisons, profils de livreurs, suivi en temps réel, chat et paiements.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

    ## Bienvenue dans l'API Allo Delivery

    Cette API permet d'interagir avec la plateforme de livraison Allo Delivery.
    Elle expose des endpoints pour l'authentification, la gestion des demandes de livraison,
    le suivi en temps réel, le chat entre clients et livreurs, ainsi que le tableau de bord livreur.

    <aside>
    Tous les endpoints protégés nécessitent un token Sanctum Bearer.
    Récupérez votre token via les endpoints `/api/register` ou `/api/login`.
    </aside>

