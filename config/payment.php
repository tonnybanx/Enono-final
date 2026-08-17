<?php
/**
 * Payment Configuration for Enono
 * --------------------------------
 * Switch between simulated and real payment providers here.
 * Keep API keys and secrets out of version control in production.
 */

// ---------------------------------------------------------------------------
// MODE: 'simulated' | 'real'
// ---------------------------------------------------------------------------
define('PAYMENT_MODE', 'simulated');

// ---------------------------------------------------------------------------
// Supported payment methods (shown on checkout)
// key => [label, icon_url, enabled]
// ---------------------------------------------------------------------------
$PAYMENT_METHODS = [
    'mtn' => [
        'label'    => 'MTN MoMo',
        'icon'     => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRzmowaypHadzjiejbyAMF-bK8F1ABsHG5vN-QPjFiD5Q&s',
        'enabled'  => true,
    ],
    'airtel' => [
        'label'    => 'Airtel Money',
        'icon'     => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQuYhGSJ4PKawoeO0WWHzoKp3fi5VBxiyZDIjvO43t_Dw&s=10',
        'enabled'  => true,
    ],
    'bank' => [
        'label'    => 'Bank Transfer',
        'icon'     => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSFi-6ddHxd0--NAK0O1mnkboFueeozBHzDpexhP2VS1A&s=10',
        'enabled'  => true,
    ],
];

// ---------------------------------------------------------------------------
// SIMULATED PAYMENT MODULE (always available for development / testing)
// ---------------------------------------------------------------------------
function processSimulatedPayment(array $orderData): array
{
    // Simulate a short processing delay
    usleep(300000); // 0.3 s

    // Always succeed in simulation (you can force failure by setting a flag)
    $forceFail = false; // change to true to test failure path

    if ($forceFail) {
        return [
            'success'       => false,
            'transaction_id'=> null,
            'message'       => 'Simulated payment declined.',
            'raw'           => null,
        ];
    }

    $txId = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));

    return [
        'success'        => true,
        'transaction_id' => $txId,
        'message'        => 'Payment simulated successfully.',
        'raw'            => [
            'mode'   => 'simulated',
            'method' => $orderData['payment_method'] ?? null,
            'amount' => $orderData['total'] ?? 0,
        ],
    ];
}

// ---------------------------------------------------------------------------
// REAL PAYMENT MODULE (commented out – enable when you have live credentials)
// ---------------------------------------------------------------------------
/*
function processRealPayment(array $orderData): array
{
    // Example structure for MTN MoMo / Airtel Money / bank gateway.
    // Replace with the official SDK or HTTP client of your chosen provider.

    $method = $orderData['payment_method'] ?? '';
    $amount = (float)($orderData['total'] ?? 0);
    $phone  = $orderData['customer_phone'] ?? '';
    $ref    = $orderData['order_number'] ?? '';

    // --- MTN MoMo example (pseudo-code) ---
    if ($method === 'mtn') {
        // $client = new MtnMomoClient([
        //     'subscription_key' => getenv('MTN_SUBSCRIPTION_KEY'),
        //     'api_user'         => getenv('MTN_API_USER'),
        //     'api_key'          => getenv('MTN_API_KEY'),
        //     'environment'      => 'production', // or 'sandbox'
        // ]);
        // $response = $client->requestToPay([
        //     'amount'       => $amount,
        //     'currency'     => 'UGX',
        //     'externalId'   => $ref,
        //     'payer'        => ['partyIdType' => 'MSISDN', 'partyId' => $phone],
        //     'payerMessage' => 'Enono order ' . $ref,
        //     'payeeNote'    => 'Enono Market',
        // ]);
        // return [
        //     'success'        => $response['status'] === 'SUCCESSFUL',
        //     'transaction_id' => $response['financialTransactionId'] ?? null,
        //     'message'        => $response['message'] ?? '',
        //     'raw'            => $response,
        // ];
    }

    // --- Airtel Money example (pseudo-code) ---
    if ($method === 'airtel') {
        // Similar pattern using Airtel Money Open API
    }

    // --- Bank Transfer (manual / instruction-based) ---
    if ($method === 'bank') {
        // Usually just record the intention; settlement is manual.
        return [
            'success'        => true,
            'transaction_id' => 'BANK-PENDING-' . $ref,
            'message'        => 'Bank transfer instructions sent. Awaiting confirmation.',
            'raw'            => null,
        ];
    }

    return [
        'success'        => false,
        'transaction_id' => null,
        'message'        => 'Unsupported payment method.',
        'raw'            => null,
    ];
}
*/

// ---------------------------------------------------------------------------
// Public entry point – call this from checkout
// ---------------------------------------------------------------------------
function processPayment(array $orderData): array
{
    if (PAYMENT_MODE === 'real') {
        // Uncomment the real function above and remove the simulated fallback
        // return processRealPayment($orderData);

        // Safety: if real is selected but function is still commented, fall back
        return processSimulatedPayment($orderData);
    }

    return processSimulatedPayment($orderData);
}