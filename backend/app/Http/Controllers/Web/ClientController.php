<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        $clients = Client::query()
            ->when(request('search'), function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('clients.form', ['client' => new Client()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Client::query()->create($this->validated($request));

        return redirect()->route('web.clients.index')->with('status', 'Cliente creado.');
    }

    public function edit(Client $client): View
    {
        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request));

        return redirect()->route('web.clients.index')->with('status', 'Cliente actualizado.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        if ($client->invoices()->exists()) {
            $client->update(['is_active' => false]);

            return back()->with('status', 'Cliente desactivado porque tiene facturas asociadas.');
        }

        $client->delete();

        return back()->with('status', 'Cliente eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
