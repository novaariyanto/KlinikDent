<footer class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                {{ now()->year }} © {{ $appName ?? config('app.name') }}
            </div>
            <div class="col-sm-6">
                <div class="text-sm-end d-none d-sm-block">
                    Made with 💖 by Kejar Koding Media
                </div>
            </div>
        </div>
    </div>
</footer>
