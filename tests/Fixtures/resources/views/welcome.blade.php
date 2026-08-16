<h1>Bienvenue {{ $name }}</h1>
<p>Nous sommes ravis de vous accueillir</p>
@if (isset($signature))
    <p>Signature: {{ $signature }}</p>
@endif
