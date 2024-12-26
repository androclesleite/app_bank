<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Api\Account;
use App\Models\Api\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\FacadesDB;

class TransactionController extends Controller
{
    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = $request->user();

        // Atualiza o saldo da conta
        $account = $user->account;
        $account->balance += $validated['amount'];
        $account->save();

        // Registra a transação
        $transaction = Transaction::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $validated['amount'],
        ]);

        return response()->json([
            'message' => 'Depósito realizado com sucesso.',
            'transaction' => $transaction,
            'balance' => $account->balance,
        ], 201);
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        // Obter o usuário autenticado
        $sender = $request->user();

        // Buscar conta do remetente
        $senderAccount = $sender->account;

        if (!$senderAccount) {
            return response()->json(['message' => 'Conta não encontrada para o remetente.'], 404);
        }

        // Verificar saldo suficiente
        if ($senderAccount->balance < $validated['amount']) {
            return response()->json(['message' => 'Saldo insuficiente para realizar a transferência.'], 400);
        }

        // Buscar conta do destinatário
        $recipient = User::find($validated['target_user_id']);
        $recipientAccount = $recipient->account;

        if (!$recipientAccount) {
            return response()->json(['message' => 'Conta não encontrada para o destinatário.'], 404);
        }

        // Iniciar a transação no banco de dados para garantir consistência
        try {
            DB::beginTransaction();

            // Atualizar saldo do remetente
            $senderAccount->balance -= $validated['amount'];
            $senderAccount->save();

            // Atualizar saldo do destinatário
            $recipientAccount->balance += $validated['amount'];
            $recipientAccount->save();

            // Registrar a transação de saída (remetente)
            $senderTransaction = Transaction::create([
                'account_id' => $senderAccount->id,
                'user_id' => $sender->id,
                'type' => 'transfer',
                'amount' => $validated['amount'],
                'reference_id' => null, // Usado para vincular reversões, se necessário
            ]);

            // Registrar a transação de entrada (destinatário)
            $recipientTransaction = Transaction::create([
                'account_id' => $recipientAccount->id,
                'user_id' => $recipient->id,
                'type' => 'transfer',
                'amount' => $validated['amount'],
                'reference_id' => $senderTransaction->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transferência realizada com sucesso.',
                'sender_balance' => $senderAccount->balance,
                'recipient_balance' => $recipientAccount->balance,
                'transactions' => [
                    'sender' => $senderTransaction,
                    'recipient' => $recipientTransaction,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao realizar a transferência.', 'error' => $e->getMessage()], 500);
        }
    }

    public function reverse(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        // Obter o usuário autenticado
        $user = $request->user();

        // Buscar a transação original
        $transaction = Transaction::where('id', $validated['transaction_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transação não encontrada ou não pertence ao usuário.'], 404);
        }

        // Verificar se já foi revertida
        $existingReverse = Transaction::where('reference_id', $transaction->id)
            ->where('type', 'reverse')
            ->exists();

        if ($existingReverse) {
            return response()->json(['message' => 'Esta transação já foi revertida.'], 400);
        }

        $account = $transaction->account;
        $recipientAccount = $transaction->recipientAccount; // Supondo que você tenha o relacionamento para a conta que recebeu o dinheiro

        if (!$account) {
            return response()->json(['message' => 'Conta de origem não encontrada para esta transação.'], 404);
        }

        if (!$recipientAccount) {
            return response()->json(['message' => 'Conta de destino não encontrada para esta transação.'], 404);
        }

        // Iniciar transação de banco de dados
        try {
            DB::beginTransaction();

            // Atualizar o saldo
            if ($transaction->type === 'deposit') {
                //$account->balance -= $transaction->amount;
                return response()->json(['message' => 'Nao pode revertir uma transação de depósito.'], 400);
            } elseif ($transaction->type === 'transfer') {
                if ($recipientAccount->balance < $transaction->amount) {
                    return response()->json(['message' => 'Saldo insuficiente na conta de destino para reverter a transação.'], 400);
                }
                $account->balance += $transaction->amount;
                $recipientAccount->balance -= $transaction->amount;
            } else {
                return response()->json(['message' => 'Tipo de transação não suportado para reversão.'], 400);
            }

            $account->save();
            $recipientAccount->save();

            // Criar a transação de reversão
            $reverseTransaction = Transaction::create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'type' => 'reverse',
                'amount' => $transaction->amount,
                'reference_id' => $transaction->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transação revertida com sucesso.',
                'reverse_transaction' => $reverseTransaction,
                'updated_balance' => $account->balance,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao reverter a transação.', 'error' => $e->getMessage()], 500);
        }
    }

    public function history(Request $request)
    {
        $user = $request->user();

        // Buscar todas as transações associadas à conta do usuário
        $transactions = Transaction::where('user_id', $user->id)
            ->with(['account'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Histórico de transações recuperado com sucesso.',
            'transactions' => $transactions,
        ], 200);
    }
}
