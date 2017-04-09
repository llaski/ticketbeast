<?php

namespace App;

use App\Billing\Charge;
use App\Facades\OrderConfirmationNumber;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    public function concert()
    {
        return $this->belongsTo(Concert::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function ticketQuantity()
    {
        return $this->tickets()->count();
    }

    public static function forTickets($tickets, $email, Charge $charge)
    {
        $order = self::create([
            'confirmation_number' => OrderConfirmationNumber::generate(),
            'email' => $email,
            'amount' => $charge->amount(),
            'card_last_four' => $charge->cardLastFour()
        ]);

        $tickets->each->claimFor($order);

        return $order;
    }

    public static function findByConfirmationNumber($confirmationNumber)
    {
        return self::where('confirmation_number', $confirmationNumber)->firstOrFail();
    }

    public function toArray()
    {
        return [
            'confirmation_number' => $this->confirmation_number,
            'email' => $this->email,
            'amount' => $this->amount,
            'ticket_quantity' => $this->ticketQuantity(),
            'tickets' => $this->tickets->map(function($ticket){
                return ['code' => $ticket->code];
            })->all()
        ];
    }
}
