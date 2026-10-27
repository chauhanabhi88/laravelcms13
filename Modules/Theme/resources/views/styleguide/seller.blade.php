@extends('theme::layouts.seller.master')

@section('title', 'Dashboard preview')

@section('page_actions')
    <x-theme::button type="button" size="sm"><x-theme::icon name="plus" size="size-4" /> Add catalog</x-theme::button>
@endsection

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-theme::stat-card label="Orders today" value="128" :delta="12.5" icon="package" />
            <x-theme::stat-card label="Pending acceptance" value="14" icon="clock" href="#" />
            <x-theme::stat-card label="Ready to ship" value="32" icon="truck" />
            <x-theme::stat-card label="Upcoming payout" value="₹48,250" icon="wallet" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-card border border-line bg-surface p-5 shadow-card lg:col-span-2">
                <h2 class="text-base font-semibold text-ink">Onboarding</h2>
                <x-theme::stepper class="mt-4" :steps="['Business', 'GSTIN', 'Bank', 'Warehouse', 'Agreement']" :current="3" />
            </div>
            <div class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">Action needed</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li class="flex items-center justify-between"><span class="text-ink-soft">Pending acceptance</span><x-theme::chip variant="danger">14</x-theme::chip></li>
                    <li class="flex items-center justify-between"><span class="text-ink-soft">SLA at risk</span><x-theme::chip variant="deal">3</x-theme::chip></li>
                    <li class="flex items-center justify-between"><span class="text-ink-soft">Rejected catalogs</span><x-theme::chip>2</x-theme::chip></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
