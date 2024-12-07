<x-mail::message>
    # Order Placed Successfully!

    Thank you for your order. We have received your order and will process it shortly.

    <x-mail::button :url="$url">
        Button Text
    </x-mail::button>

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>
