<?php

class ArbitrumRPC
{
    private $rpcUrl;
    private $maxRetries = 3;

    // -----------------------------------------------------------------
    // Event Topics
    // -----------------------------------------------------------------
    private const TRANSFER_TOPIC = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef";

    private const SWAP_TOPICS = [
        // Uniswap V2 (also PancakeSwap V2, Camelot V2)
        "0xd78ad95fa46c994b6551d0da85fc275fe613ce37657fb8d5e1b1f8f4d5f3f0f0",
        // Uniswap V3 (also PancakeSwap V3)
        "0xc42079f94a6350d7e6235f29174924f928cc2ac818eb64fedc7e13c0bcaeea52",
        // Camelot V3 (Algebra)
        "0x93f28a41b8a4fa2c8a9b6f4e9f7e6c92b2f9b391d4b2666ebadc177a64eabf04"
    ];

    // -----------------------------------------------------------------
    // Function Selectors
    // -----------------------------------------------------------------
    private const DECIMALS_SELECTOR = "0x313ce567";
    private const NAME_SELECTOR     = "0x06fdde03";
    private const SYMBOL_SELECTOR   = "0x95d89b41";
    private const TOKEN0_SELECTOR   = "0x0dfe1681";
    private const TOKEN1_SELECTOR   = "0xd21220a7";
    private const FACTORY_SELECTOR  = "0xc45a0155";

    // -----------------------------------------------------------------
    // Known DEX Factories (Arbitrum)
    // -----------------------------------------------------------------
    private $factories = [
        "0x1F98431c8aD98523631AE4a59f267346ea31F984" => "Uniswap V3",
        "0x5c69bee701ef814a2b6a3edd4b1652cb9cc5aa6f" => "Uniswap V2",
        "0x0BFbCF9fa4f9C56B0F40a671Ad40E0805A091865" => "PancakeSwap V3",
        "0xca143ce32fe78f1f7019d7d551a6402fc5350c73" => "PancakeSwap V2",
        "0x6eccab422d763ac031210895c81787e87b43a652" => "Camelot V2"
    ];

    // -----------------------------------------------------------------
    // Known Routers (Arbitrum)
    // -----------------------------------------------------------------
    private $routers = [
        "0xEf1c6E67703c7BD7107eed8303Fbe6EC2554BF6B" => "Uniswap Universal Router 2",
        "0x4C60051384bd2d3C01bfc845Cf5F4b44bcbE9de5" => "Uniswap Universal Router",
        "0x3fC91A3afd70395Cd496C647d5a6CC9D4B2b7FAD" => "Uniswap Universal Router 3",
        "0x5E325eDA8064b456f4781070C0738d849c824258" => "Uniswap Universal Router 4",
        "0x4752ba5DBc23f44D87826276BF6Fd6b1C372aD24" => "Uniswap V2 Router",
        "0xE592427A0AEce92De3Edee1F18E0157C05861564" => "Uniswap V3 Router",
        "0x68b3465833fb72A70ecDF485E0e4C7bD8665Fc45" => "Uniswap V3 Router 2"
    ];

    // -----------------------------------------------------------------
    // Label registry & cache
    // -----------------------------------------------------------------
    private $labels = [
        "0x0000000000000000000000000000000000000000" => "Zero Address"
    ];
    private $decimalsCache = [];
	
	public function __construct($rpcUrl) {
        $this->rpcUrl = $rpcUrl;
    }
	
    // -----------------------------------------------------------------
    // Core RPC caller
    // -----------------------------------------------------------------
    private function call($method, $params = [])
    {
        $payload = [
            "jsonrpc" => "2.0",
            "method"  => $method,
            "params"  => $params,
            "id"      => rand(1, 99999)
        ];

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            $ch = curl_init($this->rpcUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
                CURLOPT_TIMEOUT        => 15
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $errno = curl_errno($ch);
                $error = curl_error($ch);
                curl_close($ch);
                if ($errno == 28) continue; // timeout → retry
                return ["error" => ["type" => "curl", "message" => $error]];
            }
            curl_close($ch);

            $decoded = json_decode($response, true);
            if (!$decoded) {
                return ["error" => ["type" => "json", "message" => "Invalid JSON"]];
            }
            if (isset($decoded['error'])) {
                return [
                    "error" => [
                        "type"    => "rpc",
                        "code"    => $decoded['error']['code'] ?? null,
                        "message" => $decoded['error']['message'] ?? "RPC Error"
                    ]
                ];
            }
            return $decoded;
        }
        return ["error" => ["type" => "timeout", "message" => "RPC timed out"]];
    }

    public function getTransaction($txHash)
    {
        return $this->call("eth_getTransactionByHash", [$txHash]);
    }

    public function getTransactionReceipt($txHash)
    {
        return $this->call("eth_getTransactionReceipt", [$txHash]);
    }

    // -----------------------------------------------------------------
    // Big number helpers (with fallback)
    // -----------------------------------------------------------------
    private function hexToDec($hex)
    {
        $hex = str_replace("0x", "", strtolower($hex));
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }
        if (function_exists('bcadd')) {
            $dec = "0";
            foreach (str_split($hex) as $char) {
                $dec = bcmul($dec, "16");
                $dec = bcadd($dec, hexdec($char));
            }
            return $dec;
        }
        return null;
    }

    private function formatUnits($raw, $decimals)
    {
        if (!function_exists('bcdiv')) return $raw;
        $divisor = bcpow("10", (string)$decimals);
        return bcdiv($raw, $divisor, $decimals);
    }

    // -----------------------------------------------------------------
    // Token decimals (cached)
    // -----------------------------------------------------------------
    public function getTokenDecimals($contract)
    {
        $contract = strtolower($contract);
        if (isset($this->decimalsCache[$contract])) {
            return $this->decimalsCache[$contract];
        }
        $call = ["to" => $contract, "data" => self::DECIMALS_SELECTOR];
        $result = $this->call("eth_call", [$call, "latest"]);
        if (!isset($result['result'])) {
            return 18;
        }
        $decimals = hexdec($result['result']);
        $this->decimalsCache[$contract] = $decimals;
        return $decimals;
    }

    // -----------------------------------------------------------------
    // Address labeling
    // -----------------------------------------------------------------
    public function labelAddress($address)
    {
        $address = strtolower($address);
        return $this->labels[$address] ?? null;
    }

    // -----------------------------------------------------------------
    // ERC20 Transfer decoding
    // -----------------------------------------------------------------
    public function decodeTokenTransfers($receipt)
    {
        $transfers = [];
        if (!isset($receipt['result']['logs'])) {
            return $transfers;
        }

        foreach ($receipt['result']['logs'] as $log) {
            if (!isset($log['topics'][0])) continue;
            if (strtolower($log['topics'][0]) !== self::TRANSFER_TOPIC) continue;

            $from = "0x" . substr($log['topics'][1], 26);
            $to   = "0x" . substr($log['topics'][2], 26);
            $raw  = $this->hexToDec($log['data']);
            $decimals = $this->getTokenDecimals($log['address']);
            $human = $this->formatUnits($raw, $decimals);

            $transfers[] = [
                "token_contract" => $log['address'],
                "from"           => $from,
                "from_label"     => $this->labelAddress($from),
                "to"             => $to,
                "to_label"       => $this->labelAddress($to),
                "value_raw"      => $raw,
                "decimals"       => $decimals,
                "value_human"    => $human
            ];
        }
        return $transfers;
    }

    // -----------------------------------------------------------------
    // Simple swap detection (by log topic)
    // -----------------------------------------------------------------
    public function detectSwap($receipt)
    {
        if (!isset($receipt['result']['logs'])) return false;
        foreach ($receipt['result']['logs'] as $log) {
            if (!isset($log['topics'][0])) continue;
            $topic0 = strtolower($log['topics'][0]);
            foreach (self::SWAP_TOPICS as $swapTopic) {
                if ($topic0 === strtolower($swapTopic)) {
                    return true;
                }
            }
        }
        return false;
    }

    // -----------------------------------------------------------------
    // Advanced swap detection (multiple token contracts moved)
    // -----------------------------------------------------------------
    public function detectSwapAdvanced($transfers)
    {
        if (count($transfers) < 2) return false;
        $contracts = [];
        foreach ($transfers as $t) {
            $contracts[$t['token_contract']] = true;
        }
        return count($contracts) > 1;
    }

    // -----------------------------------------------------------------
    // Pool introspection
    // -----------------------------------------------------------------
    private function getPoolTokens($pool)
    {
        $call0 = ["to" => $pool, "data" => self::TOKEN0_SELECTOR];
        $call1 = ["to" => $pool, "data" => self::TOKEN1_SELECTOR];
        $res0 = $this->call("eth_call", [$call0, "latest"]);
        $res1 = $this->call("eth_call", [$call1, "latest"]);
        if (!isset($res0['result']) || !isset($res1['result'])) return [null, null];
        $token0 = "0x" . substr($res0['result'], 26);
        $token1 = "0x" . substr($res1['result'], 26);
        return [$token0, $token1];
    }

    private function getPoolFactory($pool)
    {
        $call = ["to" => $pool, "data" => self::FACTORY_SELECTOR];
        $res = $this->call("eth_call", [$call, "latest"]);
        if (!isset($res['result'])) return null;
        return strtolower("0x" . substr($res['result'], 26));
    }

    private function detectDexFromPool($pool)
    {
        $factory = $this->getPoolFactory($pool);
        if (!$factory) return null;
        return $this->factories[$factory] ?? "Unknown DEX";
    }

    // -----------------------------------------------------------------
    // Detailed swap analysis (V2/V3)
    // -----------------------------------------------------------------
    public function analyzeSwap($receipt)
    {
        if (!isset($receipt['result']['logs'])) return null;

        foreach ($receipt['result']['logs'] as $log) {
            $topic0 = strtolower($log['topics'][0] ?? '');
            if (!in_array($topic0, array_map('strtolower', self::SWAP_TOPICS))) continue;

            $pool = $log['address'];
            $dex = $this->detectDexFromPool($pool);
            list($token0, $token1) = $this->getPoolTokens($pool);
            if (!$token0 || !$token1) continue;

            $data = str_replace("0x", "", $log['data']);
            $dataLen = strlen($data);

            if ($dataLen == 256) { // V2 style
                $amount0In  = $this->hexToDec(substr($data, 0, 64));
                $amount1In  = $this->hexToDec(substr($data, 64, 64));
                $amount0Out = $this->hexToDec(substr($data, 128, 64));
                $amount1Out = $this->hexToDec(substr($data, 192, 64));

                $inputToken  = $amount0In > 0 ? $token0 : $token1;
                $inputAmount = $amount0In > 0 ? $amount0In : $amount1In;
                $outputToken = $amount0Out > 0 ? $token0 : $token1;
                $outputAmount = $amount0Out > 0 ? $amount0Out : $amount1Out;
            } else { // V3 style (or other)
                $amount0 = $this->hexToDec(substr($data, 0, 64));
                $amount1 = $this->hexToDec(substr($data, 64, 64));
                // In V3, amounts can be negative (two's complement), but we treat absolute values.
                $inputToken  = $amount0 > 0 ? $token0 : $token1;
                $inputAmount = $amount0 > 0 ? $amount0 : $amount1;
                $outputToken = $amount0 < 0 ? $token0 : $token1;
                $outputAmount = $amount0 < 0 ? abs($amount0) : abs($amount1);
            }

            $decIn = $this->getTokenDecimals($inputToken);
            $decOut = $this->getTokenDecimals($outputToken);

            return [
                "dex"    => $dex,
                "pool"   => $pool,
                "input"  => [
                    "token"  => $inputToken,
                    "amount" => $this->formatUnits($inputAmount, $decIn)
                ],
                "output" => [
                    "token"  => $outputToken,
                    "amount" => $this->formatUnits($outputAmount, $decOut)
                ]
            ];
        }
        return null;
    }

    // -----------------------------------------------------------------
    // Router detection
    // -----------------------------------------------------------------
    public function detectRouter($tx)
    {
        $to = strtolower($tx['result']['to'] ?? '');
        return $this->routers[$to] ?? null;
    }

    // -----------------------------------------------------------------
    // Gas cost calculation
    // -----------------------------------------------------------------
    public function calculateGasCost($receipt)
    {
        if (!isset($receipt['result'])) return null;
        $gasUsed  = $this->hexToDec($receipt['result']['gasUsed'] ?? "0x0");
        $gasPrice = $this->hexToDec($receipt['result']['effectiveGasPrice'] ?? "0x0");
        if (!function_exists('bcmul')) return null;

        $wei = bcmul($gasUsed, $gasPrice);
        $eth = bcdiv($wei, bcpow("10", "18"), 18);
        return ["wei" => $wei, "eth" => $eth];
    }
}

// -----------------------------------------------------------------
// Usage - combined output
// -----------------------------------------------------------------
$rpc = new ArbitrumRPC("https://arbitrum-one-rpc.publicnode.com");

$txHash = $_GET['tx'] ?? null;
if (!$txHash) {
    die("Provide transaction hash via ?tx=0x...");
}
if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
    die("Invalid transaction hash.");
}

$transaction = $rpc->getTransaction($txHash);
$receipt     = $rpc->getTransactionReceipt($txHash);

$transfers     = $rpc->decodeTokenTransfers($receipt);
$gasCost       = $rpc->calculateGasCost($receipt);
$isSwap        = $rpc->detectSwap($receipt);
$isSwapAdvanced = $rpc->detectSwapAdvanced($transfers);
$swapAnalysis  = $rpc->analyzeSwap($receipt);
$router        = $rpc->detectRouter($transaction);

// Combine swap flags
if (!$isSwap && $isSwapAdvanced) {
    $isSwap = true;
}

header('Content-Type: application/json');
echo json_encode([
    "tx_raw"            => $transaction,
    "receipt_raw"       => $receipt,
    "token_transfers"   => $transfers,
    "gas_cost"          => $gasCost,
    "is_swap"           => $isSwap,
    "is_swap_advanced"  => $isSwapAdvanced,
    "swap_analysis"     => $swapAnalysis,
    "router"            => $router
], JSON_PRETTY_PRINT);
