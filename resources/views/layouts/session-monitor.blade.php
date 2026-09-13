@auth
<script src="{{ asset('js/session.js') }}" data-check="{{ route('session.status') }}" data-login="{{ route('login') }}" data-timeout="{{ (int) config('session.lifetime') * 60 }}" defer></script>
@endauth
