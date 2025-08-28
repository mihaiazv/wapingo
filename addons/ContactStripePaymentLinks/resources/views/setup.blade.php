@extends('layouts.app', ['title' => __tr('Addon License Information')])
@section('content')
@include('users.partials.header', [
'title' => __tr('Addon License Information',[], false),
'description' =>'',
'class' => 'col-lg-7'
])
<div class="container-fluid ">
    <div class="row">
        <div class="col-xl-12 mb-3">
            <div class="float-right">
                <a class="lw-btn btn btn-dark" href="{{ route('central.addons.read.list') }}">{{
                    __tr('Back to Addons') }}</a>
            </div>
        </div>
        <!-- button -->
    <div class="col-xl-8 mb-3 offset-xl-2 col-lg-10 offset-lg-1 col-md-12">
        <div class="card col shadow-none p-4 mb-4 text-center">
            <h2 class="card-header">
                {{ __tr('Addon - __addonName__', [
                    '__addonName__' => $addonMetadata['title']
                ]) }}
            </h2>
            <div class="card-body col-sm-12 col-md-4 col-md-4 offset-md-4 py-4">
                <img src="{{ route('addon.'. $addon.'.assets', [
            'path' => 'addon.png'
            ]) }}" class="card-img-top" alt="...">
            </div>
            <div class="card-body text-center">
                <p class="card-text">{{ $addonMetadata['description'] ?? '' }}</p>
            </div>
            <div class="card-footer text-center">
                <div class="btn-group">
                    @if ($addonMetadata['purchase_link'] ?? null)
                        <a class="lw-btn btn btn-info btn-sm" target="_blank" href="{{ $addonMetadata['purchase_link'] }}">{{
                    __tr('Purchase Page Link') }}</a>
                    @endif
                    @if ($addonMetadata['video_link'] ?? null)
                        <a class="lw-btn btn btn-info btn-sm" target="_blank" href="{{ $addonMetadata['video_link'] }}">{{
                    __tr('Video') }}</a>
                    @endif
                    @if ($addonMetadata['more_info'] ?? null)
                        <a class="lw-btn btn btn-info btn-sm" target="_blank" href="{{ $addonMetadata['more_info'] }}">{{
                    __tr('More Info') }}</a>
                    @endif
                    @if ($addonMetadata['help_document'] ?? null)
                        <a class="lw-btn btn btn-info btn-sm" target="_blank" href="{{ $addonMetadata['help_document'] }}">{{
                    __tr('Help') }}</a>
                    @endif
                </div>
            </div>
        </div>
@if($addonLicInfo('registration_id'))
<div class="text-center mt-2 card py-2">
	@if(sha1(array_get($_SERVER, 'HTTP_HOST', '') . $addonLicInfo('registration_id') . '1.0+') !== $addonLicInfo('signature'))
			<div class="my-5 text-danger">
				<i class="fas fa-exclamation-triangle fa-6x mb-4 text-warning"></i>
				<h2> <strong><?= __tr('Invalid Signature') ?></strong></h2>
				<h3><?= __tr('Please remove and verify the licence again.') ?></h3>
			</div>
	@else
	<div class="my-2 text-success">
		<i class="fas fa-award fa-3x mb-4 text-success"></i>
		<h2> <strong><?= __tr('Congratulation') ?></strong></h2>
		<h3><?= __tr('you have successfully verified the licence for addon') ?></h3>
	</div>
	@endif
	<strong><?= __tr('Last verified on') ?></strong> <br> <?= formatDate($addonLicInfo('registered_at')) ?> (<?= formatDiffForHumans($addonLicInfo('registered_at')) ?>)
		<div class="mt-3">
			<strong><?= __tr('Version') ?></strong> <br> <?= config('lwSystem-'. $addon .'.version') ?>
		</div>
		<div class="mt-3">
		<a class="lw-ajax-link-action-via-confirm btn btn-danger btn-sm" data-confirm="<?= __tr('Are you sure you want to remove licence') ?>" href="<?= route('addon.'. $addon .'.processAddonDeactivation') ?>?pageType=lwAddon{{ $addon }}" data-callback="__Utils.viewReload" id="alertsDropdown" role="button" data-method="post">
            <i class="fas fa-trash"></i> <?= __tr('Remove Licence') ?>
        </a>
	</div>
</div>
@else
<!-- Email Setting Form -->
<div class="col-12 mb-3 alert alert-warning">
<?= __tr('Thank you for purchase of our product. Please activate it using Envato purchase code.') ?> <br><small><a target="_blank" href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-"><?= __tr('Where Is My Purchase Code?') ?></a></small>
</div>
<div class="lw-container">
	<div class="lw-container-box">
		<?= __tr('Initializing please wait ...') ?>
	</div>
</div>

</div>
</div>
@push('appScripts')
<script>
        (function($) {
        'use strict';
	// Get third party Url from config and customer uid from store setting table
	var appUrl = "<?= config('lwSystem-'. $addon .'.app_update_url') ?>/api/app-update",
		registrationId = "<?= config('lwSystem-'. $addon .'.registration_id') ?>",
		version = "<?= config('lwSystem-'. $addon .'.version') ?>",
		productUid = "<?= config('lwSystem-'. $addon .'.product_uid') ?>",
		csrfToken = "<?= csrf_token() ?>",
		localRegistrationRoute = "<?= route('addon.'. $addon .'.processAddonActivation') ?>";
	// Set up ajax request header
	$.ajaxSetup({
		headers: {
			'X-CSRF-TOKEN': csrfToken,
		}
	});

	// Check if existing customer not found then get product update registration form.
	if (!registrationId) {
		// Request for product update registration
		$.post(appUrl + '/register-purchase-form', {
			'current_version': version,
			'product_uid': productUid
		}, function(data, status) {
			try {
				data = JSON.parse(data);
				$(".lw-container-box").html(data.html);
			} catch (error) {
				$(".lw-container-box").html(data);
			}
			$('#productUpdateForm').validate()
		});

		// Process for register update product
		$('body .lw-container-box').on('submit', '#productUpdateForm', function(e) {
			e.preventDefault();
			$.post(appUrl + '/register-purchase',
				$('#productUpdateForm').serialize(),
				function(responseData) {
					var requestData = responseData.data;
					if ((responseData.reaction === 1)) {
						registrationId = requestData.registration_id;
						$.post(localRegistrationRoute, requestData, function(data) {
							if (data.reaction === 21) {
								window.location = "<?= route('addon.'. $addon .'.setup_view') ?>";
							}
						});
					} else {
						$('.lw-error-container-box').remove();
						if (requestData.message) {
							$(".lw-container-box").prepend('<div class="alert alert-danger lw-error-container-box"> ' + requestData.message + ' </div>');
						}
						if (requestData.validation) {
							$.each(requestData.validation, function(key, value) {
								$('#' + key).parent().find('.error').remove();
								$('#' + key).parent().append('<label id="' + key + '-error" class="error" for="' + key + '">' + value + '</label>')
							});
						}
					}
				}, 'JSON');
		});
		// If existing customer then show check for update form.
	}
})(jQuery);
</script>
@endpush
@endif
        </div>
    </div>
</div>
@endsection()