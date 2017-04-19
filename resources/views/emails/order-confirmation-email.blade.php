<p>Thanks for your order!</p>

<p>You can view your tickets at anytime by visiting this URL:</p>

<p>
    <a href="{{ url("/orders/{$order->confirmation_number}") }}">{{ url("/orders/{$order->confirmation_number}") }}</a>
</p>