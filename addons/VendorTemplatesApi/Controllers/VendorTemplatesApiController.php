<?php

namespace Addons\VendorTemplatesApi\Controllers;

use App\Yantrana\Base\BaseController;
use App\Yantrana\Base\BaseRequestTwo;
use App\Yantrana\Components\WhatsAppService\WhatsAppTemplateEngine;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

class VendorTemplatesApiController extends BaseController
{
    protected WhatsAppTemplateEngine $whatsAppTemplateEngine;

    public function __construct(WhatsAppTemplateEngine $whatsAppTemplateEngine)
    {
        $this->whatsAppTemplateEngine = $whatsAppTemplateEngine;
    }

    protected function apiAccessAllowedOrAbort($vendorId = null): void
    {
        $vendorId = $vendorId ?: getVendorId();
        $vendorPlanDetails = vendorPlanDetails('api_access', 0, $vendorId);
        abortIf(!Arr::get($vendorPlanDetails, 'is_limit_available'), 401, 'API access is not available in your plan, please upgrade your subscription plan.');
    }

    public function index(BaseRequestTwo $request, string $vendorUid)
    {
        $this->apiAccessAllowedOrAbort();
        validateVendorAccess('manage_templates');

        $templatesResponse = $this->whatsAppTemplateEngine->prepareApprovedTemplates();

        $templates = collect($templatesResponse->data('whatsAppTemplates'))
            ->map(function ($template) {
                $templateArray = is_array($template) ? $template : $template->toArray();
                $createdAt = data_get($template, 'created_at');
                $updatedAt = data_get($template, 'updated_at');

                return [
                    'uid' => Arr::get($templateArray, '_uid'),
                    'name' => Arr::get($templateArray, 'template_name'),
                    'category' => Arr::get($templateArray, 'category'),
                    'language' => Arr::get($templateArray, 'language'),
                    'status' => Arr::get($templateArray, 'status'),
                    'template_id' => Arr::get($templateArray, 'template_id'),
                    'components' => Arr::get($templateArray, '__data.template.components', []),
                    'created_at' => $createdAt instanceof CarbonInterface ? $createdAt->toIso8601String() : $createdAt,
                    'updated_at' => $updatedAt instanceof CarbonInterface ? $updatedAt->toIso8601String() : $updatedAt,
                ];
            })
            ->values()
            ->toArray();

        return $this->processApiResponse($templatesResponse, [
            'templates' => $templates,
        ]);
    }
}
