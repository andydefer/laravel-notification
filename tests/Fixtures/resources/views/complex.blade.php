<h1>Bonjour {{ $user->name }}</h1>
<ul>
    @foreach ($items as $item)
        <li>{{ $item['name'] }} - {{ $item['price'] }} €</li>
    @endforeach
</ul>
<p>Total: {{ $total }} €</p>
<p>Date: {{ $date }}</p>
