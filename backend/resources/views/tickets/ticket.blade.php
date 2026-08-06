<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Ticket {{ $deliveryRequest->tracking_number }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
        }

        .header {
            border-bottom: 3px solid #0ea5e9;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            color: #0ea5e9;
        }

        .badge {
            background: #0ea5e9;
            color: #ffffff;
            padding: 3px 8px;
            font-size: 11px;
            margin-left: 8px;
        }

        .muted {
            color: #6b7280;
            font-size: 11px;
            margin-top: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            width: 30%;
            font-weight: normal;
            color: #374151;
        }

        .tracking {
            font-size: 16px;
            font-weight: bold;
        }

        .amount {
            font-weight: bold;
            color: #047857;
        }

        .footer {
            margin-top: 24px;
            font-size: 10px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        <span class="title">{{ $deliveryRequest->driver?->driverProfile?->brand_name ?? 'Allo Delivery' }}</span>
        <span class="badge">{{ $statusLabel }}</span>
        <div class="muted">
            Ticket de livraison — créé le {{ $deliveryRequest->created_at?->format('d/m/Y H:i') }}
        </div>
    </div>

    <table>
        <tr>
            <th>Numéro de suivi</th>
            <td class="tracking">{{ $deliveryRequest->tracking_number }}</td>
        </tr>
        <tr>
            <th>Statut</th>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <th>Prévue le</th>
            <td>{{ $deliveryRequest->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <th>Livrée le</th>
            <td>{{ $deliveryRequest->delivered_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>Expéditeur</th>
            <td>
                {{ $deliveryRequest->client?->name ?? '—' }}<br>
                {{ $deliveryRequest->client?->phone ?? '—' }}
            </td>
        </tr>
        <tr>
            <th>Destinataire</th>
            <td>
                {{ $deliveryRequest->recipient_name ?? '—' }}<br>
                {{ $deliveryRequest->recipient_phone ?? '—' }}
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <th>Adresse de retrait</th>
            <td>{{ $deliveryRequest->pickup_address ?? '—' }}</td>
        </tr>
        <tr>
            <th>Adresse de livraison</th>
            <td>{{ $deliveryRequest->delivery_address ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>Service</th>
            <td>{{ $deliveryRequest->service?->name ?? '—' }}</td>
        </tr>
        <tr>
            <th>Zone</th>
            <td>
                {{ $deliveryRequest->deliveryZone?->origin_zone ?? '—' }}
                → {{ $deliveryRequest->deliveryZone?->destination_zone ?? '—' }}
            </td>
        </tr>
        <tr>
            <th>Colis</th>
            <td>{{ $deliveryRequest->package_description ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>Prix de la course</th>
            <td class="amount">{{ number_format((float) $deliveryRequest->proposed_price, 2, ',', ' ') }} DH</td>
        </tr>
        <tr>
            <th>Montant à encaisser</th>
            <td class="amount">{{ number_format((float) $deliveryRequest->amount_to_collect, 2, ',', ' ') }} DH</td>
        </tr>
        <tr>
            <th>Valeur déclarée du colis</th>
            <td>{{ number_format((float) $deliveryRequest->product_amount, 2, ',', ' ') }} DH</td>
        </tr>
    </table>

    <div class="footer">
        Allo Delivery — ticket généré automatiquement depuis l'API
    </div>
</body>

</html>
