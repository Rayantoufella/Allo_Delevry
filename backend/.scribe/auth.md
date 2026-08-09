# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_BEARER_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Token Sanctum Bearer. Récupérez-le via `/api/register` ou `/api/login` (champ `token` de la réponse).
