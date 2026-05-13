{{-- Slim, sticky top bar: brand + user dropdown. Used by the app layout. --}}
<header class="sticky top-0 z-30 flex items-center gap-4 px-10 lg:px-20 h-16 border-b border-zinc-200 bg-white/80 backdrop-blur supports-backdrop-filter:bg-white/60">
    <div class="flex items-center gap-3">
        <div class="grid place-items-center w-9 h-9 rounded-lg bg-linear-to-br from-indigo-500 to-violet-500 text-white shadow-sm">
            <flux:icon.film class="size-5" />
        </div>
        <div class="leading-tight">
            <div class="text-sm font-semibold">Recap Pipeline</div>
            <div class="text-[10px] uppercase tracking-widest text-zinc-500">Burmese · v2.0</div>
        </div>
    </div>

    <div class="flex-1"></div>

    <flux:dropdown align="end">
        <button class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-zinc-100 transition" type="button">
            <div class="grid place-items-center w-8 h-8 rounded-full bg-zinc-900 text-white text-xs font-semibold">AM</div>
            <div class="text-left leading-tight hidden sm:block">
                <div class="text-sm font-medium">Aung Min</div>
                <div class="text-[10px] uppercase tracking-widest text-zinc-500">Editor</div>
            </div>
            <flux:icon.chevron-down class="size-4 text-zinc-500" />
        </button>
        <flux:menu>
            <flux:menu.item icon="user">Profile</flux:menu.item>
            <flux:menu.item icon="cog-6-tooth">Settings</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item icon="arrow-right-start-on-rectangle" variant="danger">Sign out</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</header>
