<div class="faq" data-faq>
    @foreach ($faqs as $item)
        <details>
            <summary>{{ $item['q'] }}</summary>
            <p>{{ $item['a'] }}</p>
        </details>
    @endforeach
</div>
