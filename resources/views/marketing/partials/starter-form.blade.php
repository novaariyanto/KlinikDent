<form class="form" method="POST" action="{{ route('starter-kit.store') }}" novalidate data-track-submit="starter_kit_clicked">
    @csrf
    <p class="hp" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </p>
    @if (session('starter_kit_status'))
        <p class="alert" role="status">{{ session('starter_kit_status') }}</p>
        @if (session('starter_kit_download'))
            <a class="btn btn--primary" href="{{ route('starter-kit.download') }}" data-track="starter_kit_clicked">Unduh PDF</a>
        @endif
    @endif
    @if ($errors->starterKit->any())
        <ul class="errors">
            @foreach ($errors->starterKit->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <label>Nama
        <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>
    </label>
    <label>Nomor WhatsApp
        <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" required>
    </label>
    <label>Email
        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
    </label>
    <button class="btn btn--primary" type="submit" data-track="starter_kit_clicked">Download Starter Kit</button>
</form>
