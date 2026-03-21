<x-mail::message>
@if($type === 'register')
# {{ __('Welkom bij JudoToernooi!') }}

{{ __('Hallo :name, klik op de knop hieronder om je account te activeren.', ['name' => $name]) }}

<x-mail::button :url="$url">
{{ __('Account activeren') }}
</x-mail::button>
@else
# {{ __('Wachtwoord resetten') }}

{{ __('Klik op de knop hieronder om een nieuw wachtwoord in te stellen.') }}

<x-mail::button :url="$url">
{{ __('Nieuw wachtwoord instellen') }}
</x-mail::button>
@endif

{{ __('Deze link is :minutes minuten geldig en kan maar een keer gebruikt worden.', ['minutes' => $expiresIn]) }}

{{ __('Als je dit niet hebt aangevraagd, kun je deze email negeren.') }}

{{ __('Groetjes,') }}<br>
{{ config('app.name') }}
</x-mail::message>
