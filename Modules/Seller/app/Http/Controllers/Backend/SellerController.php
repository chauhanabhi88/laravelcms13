<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Seller\Exceptions\SellerException;
use Modules\Seller\Http\Requests\Backend\SellerDecisionRequest;
use Modules\Seller\Http\Requests\Backend\SellerUpdateRequest;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Models\SellerKycDocument;
use Modules\Seller\Repositories\SellerRepository;
use Modules\Seller\Services\BankAccountService;
use Modules\Seller\Services\KycDocumentService;
use Modules\Seller\Services\SellerVerificationService;
use Modules\User\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * All sellers: grid, detail, edit of display/contact details, suspend / reactivate,
 * KYC document viewer and manual bank verification.
 */
class SellerController extends SellerAdminController
{
    public function __construct(
        SellerRepository $sellers,
        private readonly SellerVerificationService $verification,
        private readonly BankAccountService $bankAccounts,
    ) {
        parent::__construct($sellers);
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('seller.cache.seller'), $request->input('per_page'))]);

            return view('seller::backend.seller.index', $this->gridData($request, SellerRepository::GRID_SELLERS, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('seller.cache.seller'), $request->input('per_page'))]);
            setFilterSession(config('seller.cache.seller'), $request);

            $content = view('seller::backend.seller.partials.grid', $this->gridData(
                $request,
                SellerRepository::GRID_SELLERS,
                getActiveMenuId($request, 'admin.seller.index'),
            ));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request): View|RedirectResponse
    {
        try {
            return view('seller::backend.seller.show', $this->detailData($this->findOrFail($request->route('id')), 'seller'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.seller.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            return view('seller::backend.seller.edit', [
                'seller' => $this->findOrFail($request->route('id')),
                'businessTypes' => collect((array) config('seller.business_types'))
                    ->mapWithKeys(fn (string $type): array => [$type => trans('seller::seller.business_types.'.$type)])
                    ->all(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.seller.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(SellerUpdateRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $validated = $request->validated();
            $this->findOrFail($id)->update([
                'store_name' => $validated['store_name'],
                'legal_name' => $validated['legal_name'],
                'business_type' => $validated['business_type'],
                'email' => $validated['email'],
                'phone' => $validated['phone_e164'],
                'auto_accept_orders' => (int) ($validated['auto_accept_orders'] ?? config('core.no')),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.seller.edit', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        }

        if ($request->input('snc')) {
            return redirect()->route('admin.seller.edit', updateUrlParams([$id]))->with('success', trans('seller::seller.messages.updated'));
        }

        return redirect()->route('admin.seller.show', updateUrlParams([$id]))->with('success', trans('seller::seller.messages.updated'));
    }

    public function suspend(SellerDecisionRequest $request): RedirectResponse
    {
        return $this->decide($request, fn ($seller, $admin, $reason) => $this->verification->suspend($seller, $admin, $reason), 'suspended');
    }

    public function reactivate(SellerDecisionRequest $request): RedirectResponse
    {
        return $this->decide($request, fn ($seller, $admin, $reason) => $this->verification->reactivate($seller, $admin, $reason), 'reactivated');
    }

    /**
     * Stream a KYC document of this seller (never a public URL).
     */
    public function kycView(Request $request, KycDocumentService $documents): StreamedResponse
    {
        $document = SellerKycDocument::query()
            ->where('seller_id', (int) $request->route('id'))
            ->findOrFail((int) $request->route('document'));

        return $documents->response($document);
    }

    /**
     * Accept a bank account in manual review (cheque checked, or penny-drop name mismatch explained).
     */
    public function verifyBankManually(SellerDecisionRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');
        $account = SellerBankAccount::query()->where('seller_id', $id)->findOrFail((int) $request->route('account'));

        $this->bankAccounts->markVerifiedManually($account, $this->admin(), $request->reason());

        return back()->with('success', trans('seller::seller.messages.bank_verified'));
    }

    /**
     * @param  \Closure(Seller, User, ?string): mixed  $action
     */
    private function decide(SellerDecisionRequest $request, \Closure $action, string $message): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $action($this->findOrFail($id), $this->admin(), $request->reason());
        } catch (SellerException|InvalidStateTransition $e) {
            return redirect()->route('admin.seller.show', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.seller.show', updateUrlParams([$id]))->with('error', $e->getMessage());
        }

        return redirect()->route('admin.seller.show', updateUrlParams([$id]))->with('success', trans('seller::seller.messages.'.$message));
    }
}
