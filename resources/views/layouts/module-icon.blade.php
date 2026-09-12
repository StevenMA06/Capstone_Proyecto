<svg viewBox="0 0 24 24" aria-hidden="true">
@switch($icon)
@case('home')<path d="m3 10 9-7 9 7v10H5V10m4 10v-7h6v7"/>@break
@case('users')<circle cx="12" cy="8" r="4"/><path d="M5 21v-3a7 7 0 0 1 14 0v3M3 9a3 3 0 0 1 0 6"/>@break
@case('cart')<path d="M3 3h2l3 12h11l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>@break
@case('file')<path d="M5 3h9l5 5v13H5Zm9 0v6h5M9 13h6m-6 4h6"/>@break
@case('money')<circle cx="12" cy="12" r="9"/><path d="M15 8h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9m3-10v12"/>@break
@case('box')<path d="m3 7 9-4 9 4v10l-9 4-9-4Zm0 0 9 4 9-4m-9 4v10M7 5l9 4"/>@break
@case('pin')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2"/>@break
@case('tool')<path d="m14 6 4-3a6 6 0 0 1-7 8L4 20l-3-3 9-7a6 6 0 0 1 7-7l-3 3 1 3 3 1"/>@break
@case('chart')<path d="M4 3v18h17M8 16v-5m5 5V6m5 10v-8"/>@break
@case('chat')<path d="M21 11a9 8 0 0 1-9 8H4l-1 2v-6a8 8 0 0 1 0-8 9 8 0 0 1 18 4Z"/><path d="M8 11h.01M12 11h.01M16 11h.01"/>@break
@endswitch
</svg>
