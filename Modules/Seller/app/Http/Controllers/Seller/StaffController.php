<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Seller\Http\Requests\Seller\StaffRequest;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Repositories\SellerUserRepository;

/**
 * Settings › Staff (owners only): logins with the catalog, orders or finance role.
 * Owner logins are never edited or removed here.
 */
class StaffController extends SellerPanelController
{
    public function __construct(private readonly SellerUserRepository $users) {}

    public function index(): View
    {
        $seller = $this->seller();

        return view('seller::seller.settings.staff.index', [
            'sellerStoreName' => $seller->displayName(),
            'users' => $this->users->forSeller($seller->id),
            'currentUserId' => $this->sellerUser()->id,
        ]);
    }

    public function create(): View
    {
        return view('seller::seller.settings.staff.form', [
            'sellerStoreName' => $this->seller()->displayName(),
            'staff' => null,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $staff = new SellerUser([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone_e164'],
            'role' => $validated['role'],
            'status' => (int) $validated['status'],
            'password' => $validated['password'],
        ]);
        $staff->seller()->associate($this->seller());
        $staff->save();

        return redirect()->to(frontRoute('seller.staff.index'))->with('success', trans('seller::panel.staff.messages.created'));
    }

    public function edit(Request $request): View
    {
        return view('seller::seller.settings.staff.form', [
            'sellerStoreName' => $this->seller()->displayName(),
            'staff' => $this->findStaff($request),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(StaffRequest $request): RedirectResponse
    {
        $staff = $this->findStaff($request);
        $validated = $request->validated();

        $staff->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone_e164'],
            'role' => $validated['role'],
            'status' => (int) $validated['status'],
        ]);

        if (! empty($validated['password'])) {
            $staff->password = $validated['password'];
        }

        $staff->save();

        return redirect()->to(frontRoute('seller.staff.index'))->with('success', trans('seller::panel.staff.messages.updated'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->findStaff($request)->delete();

        return redirect()->to(frontRoute('seller.staff.index'))->with('success', trans('seller::panel.staff.messages.deleted'));
    }

    /**
     * A staff login of the signed-in seller (404 for other sellers' users and for owners).
     */
    private function findStaff(Request $request): SellerUser
    {
        $staff = SellerUser::query()->findOrFail((int) $request->route('staff'));
        $this->authorizeOwned($staff);
        abort_if($staff->isOwner(), 404);

        return $staff;
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        return collect([SellerUser::ROLE_CATALOG, SellerUser::ROLE_ORDERS, SellerUser::ROLE_FINANCE])
            ->mapWithKeys(fn (string $role): array => [$role => trans('seller::seller.roles.'.$role)])
            ->all();
    }
}
