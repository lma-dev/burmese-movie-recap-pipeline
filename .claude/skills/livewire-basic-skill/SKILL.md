---
name: livewire-basic-skill
description: "Use when building or modifying components in a Laravel app — full-page Livewire routes, interactive panels (lists with filters, CRUD forms, modals), or refactoring Blade-only views into Livewire. Covers component scaffolding, state, validation, events, and pagination."
---

# Livewire basics

 Reach for Livewire whenever a page has state — filters, modals, forms, anything that re-renders on user input. Static pages stay as plain Blade.

## Creating a component

```bash
php artisan make:livewire ResourceList            # flat
php artisan make:livewire resources/list          # nested → App\Livewire\Resources\List
Generates:

app/Livewire/ResourceList.php
resources/views/livewire/resource-list.blade.php
Register a full-page component as a route:


Route::get('/resources', \App\Livewire\ResourceList::class)->name('resources.index');
Attach a layout either with #[Layout('components.layouts.app')] on the class, or by wrapping the Blade view in <x-layouts.app>. Pick whichever pattern is already used in sibling components.

Component skeleton

namespace App\Livewire;

use App\Models\Resource;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ResourceList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.resource-list', [
            'resources' => Resource::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->latest()
                ->paginate(20),
        ]);
    }
}
Conventions:

#[Url] on filter/search state so it survives refresh and shareable links.
WithPagination on any list view.
Query inside render() so reactivity works without manual refresh.
If models use HasUlids, route-model binding parameters are string, not int.
Forms + validation

use Livewire\Attributes\Validate;

#[Validate('required|string|max:120')]
public string $name = '';

#[Validate('required|numeric|min:0')]
public string $amount = '';

public function save(): void
{
    $this->validate();
    Resource::create([...]);
    $this->dispatch('resource-saved');
    $this->redirectRoute('resources.index', navigate: true);
}
Use wire:model.live only when every keystroke must hit the server (search). Otherwise plain wire:model + a submit action.
After save: dispatch an event, then redirect or close a modal.
Cross-component communication
Emit: $this->dispatch('resource-created', id: $resource->id)
Listen: #[On('resource-created')] public function refresh(): void { ... }
Parent → child: pass props via <livewire:resource-form :parent-id="$parent->id" />
Modals
Prefer the UI kit's modal component (Flux/Filament/etc.) over hand-rolled. Keep modal markup inside the Livewire component that owns its state.

View conventions
No inline <style> blocks — put styles in resources/css/app.css or use Tailwind utilities.
Don't hardcode design values (colors, spacing) — use tokens defined in app.css.
Don't introduce another JS framework. Livewire + the chosen UI kit + small Alpine snippets is enough.
Pre-finish checklist
 Filter/pagination state survives refresh (via #[Url]).
 No N+1 queries — eager-load relationships used in the view.
 No inline styles; tokens from app.css only.
 php artisan test passes; ./vendor/bin/pint is clean.
 Interactive page is a Livewire component, not a controller-rendered Blade view.
Commands

php artisan make:livewire <Name>
php artisan livewire:layout
composer dev                        # serve + queue + pail + vite
php artisan test --filter=<Name>
./vendor/bin/pint
Don't
Don't render interactive pages from a controller — make them Livewire components.
Don't put queries in computed properties without #[Computed] caching when they're expensive.
Don't reach for React/Vue/Inertia. Livewire-first.


for tailwind libaray , use shacdn library