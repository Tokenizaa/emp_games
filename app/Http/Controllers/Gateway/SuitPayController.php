<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SuitPayController extends Controller
{
    /**
     * Processar saque via modal (recebe parâmetros na URL)
     * URL: /suitpay/withdrawal/{id}/{action}
     */
    public function withdrawalFromModal($id, $action)
    {
        Log::info('=== DIVPAG: Iniciando processamento de saque ===', [
            'withdrawal_id' => $id,
            'action' => $action,
            'user_id' => auth()->id(),
            'url' => request()->fullUrl()
        ]);

        try {
            $withdrawal = Withdrawal::with('user')->find($id);
            
            if (!$withdrawal) {
                Log::error('Divpag: Saque não encontrado', ['id' => $id]);
                return redirect()->route('filament.admin.resources.todos-saques-historico.index')
                    ->with('error', 'Saque não encontrado');
            }

            if ($withdrawal->status == 1) {
                Log::warning('Divpag: Saque já processado', ['withdrawal_id' => $withdrawal->id]);
                return redirect()->route('filament.admin.resources.todos-saques-historico.index')
                    ->with('error', 'Este saque já foi processado');
            }

            // Buscar credenciais do gateway
            $gateway = Gateway::first();
            
            if (!$gateway || !$gateway->suitpay_uri || !$gateway->suitpay_cliente_id || !$gateway->suitpay_cliente_secret) {
                Log::error('Divpag: Gateway não configurado');
                return redirect()->route('filament.admin.resources.todos-saques-historico.index')
                    ->with('error', 'Gateway não configurado corretamente');
            }

            // Validar chave PIX do destinatário (usuário)
            if (!$withdrawal->pix_key) {
                Log::error('Divpag: Chave PIX não informada', ['withdrawal_id' => $withdrawal->id]);
                return redirect()->route('filament.admin.resources.todos-saques-historico.index')
                    ->with('error', 'Chave PIX do usuário não informada');
            }

            // ============================================
            // DADOS DA SUA CONTA DIVPAG (REMETENTE)
            // ============================================
            $DIVPAG_CPF = '10874294983'; // CPF da conta que ENVIA o dinheiro
            $DIVPAG_NOME = 'Cliente Cassino'; // Nome da conta que ENVIA o dinheiro
            // ============================================

            // API Divpag - Endpoint de transferência
            $apiUrl = rtrim($gateway->suitpay_uri, '/') . '/pix/payment';
            
            // Dados para a API
            $postData = [
                'client_id' => $gateway->suitpay_cliente_id,
                'client_secret' => $gateway->suitpay_cliente_secret,
                'nome' => $DIVPAG_NOME,
                'cpf' => preg_replace('/[^0-9]/', '', $DIVPAG_CPF),
                'valor' => floatval($withdrawal->amount),
                'chave_pix' => $withdrawal->pix_key,
                'urlnoty' => url('/api/suitpay/webhook')
            ];

            Log::info('Divpag: Enviando requisição de transferência', [
                'withdrawal_id' => $withdrawal->id,
                'amount' => $withdrawal->amount,
                'pix_key_destino' => $withdrawal->pix_key,
                'api_url' => $apiUrl
            ]);

            // Fazer requisição para a API
            $response = Http::asForm()->timeout(30)->post($apiUrl, $postData);
            $statusCode = $response->status();
            $responseData = $response->json();

            Log::info('Divpag: Resposta da API recebida', [
                'status_code' => $statusCode,
                'response_data' => $responseData
            ]);

            // ============================================
            // VERIFICAÇÃO CORRIGIDA - API retorna objeto direto, não array
            // ============================================
            if ($statusCode === 200 && isset($responseData['statusCode']) && $responseData['statusCode'] == 200) {
                
                $transactionId = $responseData['transactionId'] ?? null;
                $externalId = $responseData['external_id'] ?? null; // ID que o webhook vai usar
                $message = $responseData['message'] ?? 'Transferência processada';
                
                $withdrawal->update([
                    'status' => 1,
                    'bank_info' => json_encode([
                        'transaction_id' => $transactionId,
                        'external_id' => $externalId, // ← IMPORTANTE: Salvar o external_id
                        'message' => $message,
                        'pix_key' => $withdrawal->pix_key,
                        'amount' => $withdrawal->amount,
                        'processed_at' => now()->format('Y-m-d H:i:s'),
                        'processed_by' => auth()->user()->name ?? 'Sistema'
                    ])
                ]);

                Log::info('Divpag: Saque APROVADO ✅', [
                    'withdrawal_id' => $withdrawal->id,
                    'transaction_id' => $transactionId,
                    'external_id' => $externalId,
                    'amount' => $withdrawal->amount,
                    'pix_key_destino' => $withdrawal->pix_key
                ]);

                return redirect()
                    ->route('filament.admin.resources.todos-saques-historico.index')
                    ->with('success', 'Saque processado com sucesso! ID: ' . $transactionId);
            }

            // Tratamento de erros
            $errorMessage = $responseData['message'] ?? 'Erro ao processar transferência';
            
            Log::error('Divpag: ERRO ao processar transferência', [
                'withdrawal_id' => $withdrawal->id,
                'error_message' => $errorMessage,
                'full_response' => $responseData,
                'status_code' => $statusCode
            ]);

            return redirect()
                ->route('filament.admin.resources.todos-saques-historico.index')
                ->with('error', 'Erro Divpag: ' . $errorMessage);

        } catch (\Exception $e) {
            Log::error('Divpag: Exception capturada', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'withdrawal_id' => $id ?? null
            ]);

            return redirect()
                ->route('filament.admin.resources.todos-saques-historico.index')
                ->with('error', 'Erro: ' . $e->getMessage());
        }
    }

    /**
     * Processar saque via query params (backup)
     */
    public function withdrawal(Request $request)
    {
        $withdrawalId = $request->input('id');
        
        Log::info('Divpag: Redirecionando para método principal', [
            'id' => $withdrawalId
        ]);
        
        return $this->withdrawalFromModal($withdrawalId, 'user');
    }

    /**
     * Cancelar saque via modal
     */
    public function cancelWithdrawalFromModal($id, $action)
    {
        Log::info('=== DIVPAG: Iniciando cancelamento de saque ===', [
            'withdrawal_id' => $id,
            'action' => $action,
            'user_id' => auth()->id()
        ]);

        try {
            $withdrawal = Withdrawal::with('user')->find($id);
            
            if (!$withdrawal) {
                Log::error('Divpag: Saque não encontrado para cancelamento', ['id' => $id]);
                return redirect()->route('filament.admin.resources.todos-saques-historico.index')
                    ->with('error', 'Saque não encontrado');
            }

            if ($withdrawal->status == 1) {
                Log::warning('Divpag: Tentativa de cancelar saque já processado', [
                    'withdrawal_id' => $withdrawal->id
                ]);
                return redirect()->route('filament.admin.resources.todos-saques-historico.index')
                    ->with('error', 'Saque já foi pago, não pode ser cancelado');
            }

            // Devolver saldo ao usuário
            $withdrawal->user->increment('balance', $withdrawal->amount);

            $withdrawal->update([
                'status' => 2,
                'bank_info' => json_encode([
                    'cancelled_at' => now()->format('Y-m-d H:i:s'),
                    'cancelled_by' => auth()->user()->name ?? 'Sistema',
                    'amount_returned' => $withdrawal->amount
                ])
            ]);

            Log::info('Divpag: Saque CANCELADO ✅', [
                'withdrawal_id' => $withdrawal->id,
                'amount_returned' => $withdrawal->amount,
                'user_id' => $withdrawal->user_id
            ]);

            return redirect()
                ->route('filament.admin.resources.todos-saques-historico.index')
                ->with('success', 'Saque cancelado! R$ ' . number_format($withdrawal->amount, 2, ',', '.') . ' devolvido ao usuário');

        } catch (\Exception $e) {
            Log::error('Divpag: Erro ao cancelar saque', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'withdrawal_id' => $id ?? null
            ]);

            return redirect()
                ->route('filament.admin.resources.todos-saques-historico.index')
                ->with('error', 'Erro ao cancelar: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar saque via query params (backup)
     */
    public function cancelWithdrawal(Request $request)
    {
        $withdrawalId = $request->input('id');
        
        Log::info('Divpag: Redirecionando para método de cancelamento principal', [
            'id' => $withdrawalId
        ]);
        
        return $this->cancelWithdrawalFromModal($withdrawalId, 'user');
    }

    /**
     * Webhook Divpag - Processa depósitos e saques
     */
    public function webhook(Request $request)
    {
        try {
            $data = $request->all();
            
            Log::info('Divpag Webhook recebido', ['data' => $data]);

            if (!$data) {
                return response()->json(['status' => 'error'], 400);
            }

            // Verificar se os dados estão dentro de requestBody
            if (isset($data['requestBody'])) {
                $data = $data['requestBody'];
                Log::info('Divpag Webhook: Extraído de requestBody', ['data' => $data]);
            }

            $transactionType = $data['transactionType'] ?? null;

            // ============================================
            // WEBHOOK DE DEPÓSITO (Recebimento PIX)
            // ============================================
            if ($transactionType === 'RECEIVEPIX') {
                $externalId = $data['external_id'] ?? null;
                $status = $data['status'] ?? null;
                $amount = $data['amount'] ?? null;

                Log::info('Divpag Webhook: DEPÓSITO detectado', [
                    'external_id' => $externalId,
                    'status' => $status,
                    'amount' => $amount
                ]);

                if ($status === 'PAID' && $externalId) {
                    // Chamar trait para finalizar o pagamento
                    $result = \App\Traits\Gateways\SuitpayTrait::finalizePaymentViaWebhook($externalId);
                    
                    if ($result) {
                        Log::info('Divpag Webhook: Depósito confirmado ✅', [
                            'external_id' => $externalId
                        ]);
                        
                        return response()->json(['status' => 'success', 'message' => 'Depósito processado'], 200);
                    } else {
                        Log::error('Divpag Webhook: Falha ao processar depósito', [
                            'external_id' => $externalId
                        ]);
                    }
                }
            }

            // ============================================
            // WEBHOOK DE SAQUE (Transferência PIX)
            // ============================================
            if ($transactionType === 'PAYMENT') {
                $transactionId = $data['transactionId'] ?? null;
                $externalId = $data['external_id'] ?? null; // ← USAR ESTE
                $statusId = $data['statusCode']['statusId'] ?? null;

                Log::info('Divpag Webhook: SAQUE detectado', [
                    'transaction_id' => $transactionId,
                    'external_id' => $externalId,
                    'status_id' => $statusId
                ]);

                if ($statusId == 1 && $externalId) {
                    // ============================================
                    // BUSCAR PELO EXTERNAL_ID (não pelo transactionId)
                    // ============================================
                    $withdrawals = Withdrawal::whereRaw(
                        "JSON_EXTRACT(bank_info, '$.external_id') = ?", 
                        [$externalId]
                    )->get();

                    if ($withdrawals->isEmpty()) {
                        Log::warning('Divpag Webhook: Nenhum saque encontrado', [
                            'external_id' => $externalId,
                            'transaction_id' => $transactionId
                        ]);
                    }

                    foreach ($withdrawals as $withdrawal) {
                        $bankInfo = json_decode($withdrawal->bank_info, true) ?? [];
                        $bankInfo['webhook_confirmed'] = now()->format('Y-m-d H:i:s');
                        $bankInfo['webhook_transaction_id'] = $transactionId; // ID final do webhook
                        $bankInfo['webhook_status_id'] = $statusId;

                        $withdrawal->update([
                            'status' => 1,
                            'bank_info' => json_encode($bankInfo)
                        ]);

                        Log::info('Divpag Webhook: Saque confirmado ✅', [
                            'withdrawal_id' => $withdrawal->id,
                            'external_id' => $externalId,
                            'transaction_id' => $transactionId
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Divpag Webhook: Erro ao processar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}