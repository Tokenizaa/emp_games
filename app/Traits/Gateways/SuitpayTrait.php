<?php

namespace App\Traits\Gateways;

use App\Models\AffiliateHistory;
use App\Models\Deposit;
use App\Models\GamesKey;
use App\Models\Gateway;
use App\Models\Setting;
use App\Models\SuitPayPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\NewDepositNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Core as Helper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait SuitpayTrait
{
    /**
     * @var $uri
     * @var $clienteId
     * @var $clienteSecret
     */
    protected static string $uri;
    protected static string $clienteId;
    protected static string $clienteSecret;

  
    private static function generateCredentials()
    {
        $setting = Gateway::first();
        if(!empty($setting)) {
            self::$uri = $setting->getAttributes()['suitpay_uri'];
            self::$clienteId = $setting->getAttributes()['suitpay_cliente_id'];
            self::$clienteSecret = $setting->getAttributes()['suitpay_cliente_secret'];
        }
    }

    /**
     * Request QRCODE
     * Metodo para solicitar uma QRCODE PIX
     * @dev @dracman999
     * @return array
     */
public static function requestQrcode($request)
{
    try {
        // Log: Início da solicitação de QR Code
        \Log::info('[Divpag] Iniciando solicitação de QR Code...', ['request' => $request->all()]);

        // Obtendo configurações
        $setting = \Helper::getSetting();

        // Validando os dados recebidos
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:' . $setting->min_deposit, 'max:' . $setting->max_deposit],
            'cpf'    => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            \Log::warning('[Divpag] Validação falhou', ['errors' => $validator->errors()]);
            return response()->json($validator->errors(), 400);
        }

        // Gerar as credenciais
        self::generateCredentials();

        // Gerar o ID único para a transação
        $idUnico = uniqid();
        \Log::info('[Divpag] ID único gerado', ['idUnico' => $idUnico]);

        // Dados a serem enviados para gerar o QR Code
        $postData = [
            'client_id' => self::$clienteId,
            'client_secret' => self::$clienteSecret,
            'nome' => auth('api')->user()->name,
            'cpf' => \Helper::soNumero($request->input("cpf")),
            'valor' => (float) $request->input("amount"),
            'descricao' => 'Depósito via PIX',
            'urlnoty' => url('/api/suitpay/webhook'), // Webhook unificado
        ];

        // URL de requisição para a API Divpag
        $url = self::$uri . 'pix/qrcode';
        \Log::info('[Divpag] Enviando requisição para gerar QR Code', [
            'url' => $url,
            'postData' => $postData
        ]);

        // Enviar requisição para a API
        $response = Http::asForm()->post($url, $postData);

        // Log detalhado da resposta da API
        \Log::info('[Divpag] Resposta da API recebida', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        // Verificar se a resposta foi bem-sucedida
        if ($response->successful()) {
            $responseData = $response->json();
            \Log::info('[Divpag] Resposta da API processada', ['responseData' => $responseData]);

            // A API Divpag retorna transactionId e external_id
            $transactionId = $responseData['transactionId'] ?? null;
            $externalId = $responseData['external_id'] ?? null;
            
            if (!$transactionId || !$externalId) {
                \Log::error('[Divpag] transactionId ou external_id não encontrado na resposta');
                return response()->json(['error' => 'Resposta inválida da API'], 500);
            }

            \Log::info('[Divpag] IDs obtidos', [
                'transactionId' => $transactionId,
                'external_id' => $externalId
            ]);

            // Realizar a transação e o depósito dentro de uma transação DB
            DB::transaction(function () use ($transactionId, $request, $externalId) {
                \Log::info('[Divpag] Iniciando transação e depósito', [
                    'transactionId' => $transactionId,
                    'amount' => $request->input("amount"),
                    'external_id' => $externalId
                ]);

                // Salvar a transação com o external_id
                self::generateTransaction($transactionId, $request->input("amount"), $externalId);

                // Salvar o depósito com o external_id
                self::generateDeposit($transactionId, $request->input("amount"), $externalId);
            });

            // Enviar resposta com o QR Code e o externalId
            \Log::info('[Divpag] Requisição processada com sucesso', [
                'transactionId' => $transactionId,
                'qrcode' => substr($responseData['qrcode'] ?? '', 0, 50) . '...'
            ]);

            return response()->json([
                'status' => true,
                'transactionId' => $transactionId, 
                'qrcode' => $responseData['qrcode'] ?? null,
                'externalId' => $externalId 
            ]);
        }

        // Log: Falha na geração do QR Code
        \Log::error('[Divpag] Falha na geração do QR Code', [
            'status' => $response->status(),
            'response' => $response->body()
        ]);
        return response()->json(['error' => "Ocorreu uma falha ao entrar em contato com o banco."], 500);

    } catch (\Exception $e) {
        // Log: Erro inesperado
        \Log::error('[Divpag] Erro ao solicitar QR Code', [
            'message' => $e->getMessage(), 
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => 'Erro interno'], 500);
    }
}

    /**
     * Consult Status Transaction (BACKUP - caso webhook falhe)
     * Consultar o status da transação
     * @dev @dracman999
     *
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
public static function consultStatusTransaction()
{
    Log::info('[Divpag] Consultando status (backup) - últimas 5 transações');

    self::generateCredentials();

    try {
        // Buscar as últimas 5 transações que ainda não foram pagas (status != 1)
        $transactions = Transaction::where('status', '!=', 1)
            ->latest()
            ->take(5)
            ->get();

        if ($transactions->isEmpty()) {
            Log::info('[Divpag] Nenhuma transação pendente encontrada.');
            return response()->json(['message' => 'Nenhuma transação pendente'], 200);
        }

        // Filtrar transações com menos de 10 minutos
        $validTransactions = [];
        foreach ($transactions as $transaction) {
            $timeDifference = now()->diffInMinutes($transaction->updated_at);

            if ($timeDifference <= 10) {
                $validTransactions[] = $transaction->external_id;
            }
        }

        if (empty($validTransactions)) {
            Log::info('[Divpag] Nenhuma transação válida para consulta.');
            return response()->json(['message' => 'Nenhuma transação recente'], 200);
        }

        // Consultar status das transações válidas
        $responses = [];
        foreach ($validTransactions as $externalId) {
            $statusUrl = self::$uri . 'libs/consult/transaction_status?id=' . $externalId;

            Log::info('[Divpag] Consultando status', ['external_id' => $externalId]);

            $response = Http::withHeaders([
                'ci' => self::$clienteId,
                'cs' => self::$clienteSecret
            ])->get($statusUrl);

            if (!$response->successful()) {
                Log::error('[Divpag] Falha na consulta', ['external_id' => $externalId]);
                $responses[$externalId] = ['status' => 'pendente'];
                continue;
            }

            $statusData = $response->json();

            if (isset($statusData['data']['status'])) {
                $transactionStatus = $statusData['data']['status'];
                
                if ($transactionStatus === 'PAID') {
                    Log::notice('[Divpag] Pagamento confirmado via consulta', ['external_id' => $externalId]);
                    self::finalizePayment($externalId);
                }

                $responses[$externalId] = ['status' => $transactionStatus];
            }
        }

        return response()->json($responses);
    } catch (\Exception $e) {
        Log::critical('[Divpag] Erro crítico', [
            'erro' => $e->getMessage()
        ]);
        return response()->json(['error' => 'Erro interno'], 500);
    }
}

    /**
     * Finalizar pagamento via WEBHOOK
     * Chamado pelo webhook da Divpag quando o PIX é pago
     * 
     * @param string $externalId
     * @return bool
     */
    public static function finalizePaymentViaWebhook(string $externalId): bool
    {
        Log::info("[Divpag Webhook] Finalizando pagamento", ['external_id' => $externalId]);
        
        // Chama o método de finalização existente
        return self::finalizePayment($externalId);
    }

    /**
     * Finalizar Pagamento
     * @param $externalId
     * @dev @dracman999
     * @return bool
     */
    public static function finalizePayment($externalId) : bool
    {
        \Log::info("[Divpag] Iniciando finalização do pagamento com external_id: $externalId");

        // Buscar transação pelo external_id
        $transaction = Transaction::where('external_id', $externalId)->where('status', 0)->first();
        if (!$transaction) {
            \Log::error("[Divpag] Transação não encontrada para o external_id: $externalId");
            return false;
        }
        \Log::info("[Divpag] Transação encontrada", ['id' => $transaction->id]);

        $setting = \Helper::getSetting();
        $user = User::find($transaction->user_id);
        \Log::info("[Divpag] Usuário encontrado", ['id' => $user->id]);

        $wallet = Wallet::where('user_id', $transaction->user_id)->first();
        if (!$wallet) {
            \Log::error("[Divpag] Carteira não encontrada");
            return false;
        }
        \Log::info("[Divpag] Carteira encontrada");

        // Verifica se é o primeiro depósito
        $checkTransactions = Transaction::where('user_id', $transaction->user_id)
            ->where('status', 1)
            ->count();
        \Log::info("[Divpag] Transações anteriores: $checkTransactions");

        if ($checkTransactions == 0) {
            // Paga o bônus inicial
            $bonus = Helper::porcentagem_xn($setting->initial_bonus, $transaction->price);
            \Log::info("[Divpag] Pagando bônus inicial: $bonus");
            $wallet->increment('balance_bonus', $bonus);
            $wallet->update(['balance_bonus_rollover' => $bonus * $setting->rollover]);
        }

        // Rollover do depósito
        $wallet->update(['balance_deposit_rollover' => $transaction->price * intval($setting->rollover_deposit)]);
        \Log::info("[Divpag] Aplicando rollover ao depósito");

        // Acumula bônus VIP
        Helper::payBonusVip($wallet, $transaction->price);
        \Log::info("[Divpag] Pagando bônus VIP");

        if ($wallet->increment('balance', $transaction->price)) {
            \Log::info("[Divpag] Saldo do usuário atualizado");

            if ($transaction->update(['status' => 1])) {
                \Log::info("[Divpag] Status da transação atualizado para 'pago'");

                // Procura o depósito correspondente
                $deposit = Deposit::where('external_id', $externalId)->where('status', 0)->first();
                if (!empty($deposit)) {
                    \Log::info("[Divpag] Depósito encontrado", ['id' => $deposit->id]);

                    // Processa o CPA
                    $affHistoryCPA = AffiliateHistory::where('user_id', $user->id)
                        ->where('commission_type', 'cpa')
                        ->where('status', 0)
                        ->first();
                    if (!empty($affHistoryCPA)) {
                        \Log::info("[Divpag] Verificando histórico de CPA");

                        $sponsorCpa = User::find($user->inviter);
                        if (!empty($sponsorCpa)) {
                            \Log::info("[Divpag] Sponsor encontrado para CPA", ['id' => $sponsorCpa->id]);
                            if ($affHistoryCPA->deposited_amount >= $sponsorCpa->affiliate_baseline || $deposit->amount >= $sponsorCpa->affiliate_baseline) {
                                $walletCpa = Wallet::where('user_id', $affHistoryCPA->inviter)->first();
                                if (!empty($walletCpa)) {
                                    $walletCpa->increment('refer_rewards', $sponsorCpa->affiliate_cpa);
                                    $affHistoryCPA->update(['status' => 1, 'commission_paid' => $sponsorCpa->affiliate_cpa]);
                                    \Log::info("[Divpag] CPA pago ao sponsor", ['sponsor_id' => $sponsorCpa->id]);
                                }
                            } else {
                                $affHistoryCPA->update(['deposited_amount' => $transaction->price]);
                                \Log::info("[Divpag] Valor depositado atualizado no CPA");
                            }
                        }
                    }

                    if ($deposit->update(['status' => 1])) {
                        \Log::info("[Divpag] Depósito marcado como pago ✅");

                        // Notificar admins
                        $admins = User::where('role_id', 0)->get();
                        foreach ($admins as $admin) {
                            $admin->notify(new NewDepositNotification($user->name, $transaction->price));
                            \Log::info("[Divpag] Notificação enviada ao admin", ['admin_id' => $admin->id]);
                        }
                    }
                }
            }
        } else {
            \Log::error("[Divpag] Erro ao atualizar o saldo do usuário");
            return false;
        }

        return true;
    }

    /**
     * @param $idTransaction
     * @param $amount
     * @param $externalId
     * @dev @dracman999
     * @return void
     */
    private static function generateDeposit($idTransaction, $amount, $externalId)
    {
        $userId = auth('api')->user()->id;
        $wallet = Wallet::where('user_id', $userId)->first();

        Deposit::create([
            'payment_id'=> $idTransaction,
            'user_id'   => $userId,
            'amount'    => $amount,
            'type'      => 'pix',
            'currency'  => $wallet->currency,
            'symbol'    => $wallet->symbol,
            'status'    => 0,
            'external_id' => $externalId,
        ]);
    }

    /**
     * @param $idTransaction
     * @param $amount
     * @param $externalId
     * @dev @dracman999
     * @return void
     */
    private static function generateTransaction($idTransaction, $amount, $externalId)
    {
        $setting = \Helper::getSetting();

        Transaction::create([
            'payment_id' => $idTransaction,
            'user_id' => auth('api')->user()->id,
            'payment_method' => 'pix',
            'price' => $amount,
            'currency' => $setting->currency_code,
            'status' => 0,
            'external_id' => $externalId,
        ]);
    }

    /**
     * @param $request
     * @dev @dracman999
     * @return \Illuminate\Http\JsonResponse|void
     */
    public static function pixCashOut(array $array): bool
    {
        self::generateCredentials();

        $response = Http::withHeaders([
            'ci' => self::$clienteId,
            'cs' => self::$clienteSecret
        ])->post(self::$uri.'pix/payment', [
            "key" => $array['pix_key'],
            "typeKey" => $array['pix_type'],
            "value" => $array['amount'],
            'callbackUrl' => url('/api/suitpay/webhook'),
        ]);

        if($response->successful()) {
            $responseData = $response->json();

            if($responseData['response'] == 'OK') {
                $suitPayPayment = SuitPayPayment::lockForUpdate()->find($array['suitpayment_id']);
                if(!empty($suitPayPayment)) {
                    if($suitPayPayment->update(['status' => 1, 'payment_id' => $responseData['idTransaction']])) {
                        return true;
                    }
                    return false;
                }
                return false;
            }
            return false;
        }
        return false;
    }
}
