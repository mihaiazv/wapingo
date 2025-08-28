@if (getVendorSettings('lw_addon_cpl_stripe_enable'))
@push('chatRightSidebarAdditionalLinksAndButtons')
    <div class="btn-group" role="group" aria-label="Basic example">
     <a data-pre-callback="appFuncs.clearContainer" title="{{  __tr('Send Stripe Payment link') }}" class="lw-btn btn btn-sm btn-primary lw-ajax-link-action" data-response-template="#lwAddonSendPaymentLinkBody" x-bind:href="__Utils.apiURL('{{ route('vendor.contact.read.update.data', [ 'contactIdOrUid']) }}', {'contactIdOrUid': contact?._uid})" data-toggle="modal" data-target="#lwAddonSendPaymentLinkModel"><i class="fa fa-money"></i> {{  __tr('Send Stripe Payment link') }}</a> 

     <a data-pre-callback="appFuncs.clearContainer" title="{{  __tr('View Payments') }}" class="lw-btn btn btn-sm btn-dark lw-ajax-link-action" data-response-template="#lwAddonViewPaymentListBody" x-bind:href="__Utils.apiURL('{{ route('vendor.contact.read.update.data', [ 'contactIdOrUid']) }}', {'contactIdOrUid': contact?._uid})" data-toggle="modal" data-target="#lwAddonPaymentListModel"><i class="fa fa-money"></i> {{  __tr('View Payments') }}</a> 
    </div>
@endpush
{{-- this view will injected in chat blade --}}
{{-- Model for Send Payment Link --}}
@push('footer')
<x-lw.modal id="lwAddonSendPaymentLinkModel" :header="__tr('Send Stripe Payment Link')" :hasForm="true">
    @if (isDemo())
    <div class="px-4">
        <div class="alert alert-warning">
            <strong>{{  __tr('Demo Alert:') }}</strong>
            {{  __tr('Please note this is addon feature') }}
        </div>
    </div>
    @endif
    <!--  Edit Contact Form -->
    <x-lw.form id="lwAddonSendPaymentLinkForm" :action="route('addon.contact_payment_links_stripe.vendor.payment_link.write')"
        :data-callback-params="['modalId' => '#lwAddonSendPaymentLinkModel']"
        data-callback="appFuncs.modelSuccessCallback">
        <!-- form body -->
        <div id="lwAddonSendPaymentLinkBody" class="lw-form-modal-body"></div>
        <script type="text/template" id="lwAddonSendPaymentLinkBody-template">
            <input type="hidden" name="contactIdOrUid" value="<%- __tData._uid %>" />
            <x-lw.input-field type="text" id="lwSendPaymentLinkMessage"  data-form-group-class="" :label="__tr('Message if any')"  name="lw_send_payment_link_message"  />
            <x-lw.input-field type="number" id="lwSendPaymentLinkAmount"  min="1" data-form-group-class="" :label="__tr('Amount')"  name="lw_send_payment_link_amount" :appendText="getVendorSettings('lw_addon_cpl_stripe_currency_code')"  />
            <!-- form fields -->
        </script>
        <!-- form footer -->
        <div class="modal-footer">
            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary">{{ __tr('Send') }}</button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __tr('Close') }}</button>
        </div>
    </x-lw.form>
    <!--/  Edit Contact Form -->
</x-lw.modal>

{{-- Show Payment List Model --}}
<x-lw.modal id="lwAddonPaymentListModel" :header="__tr('Payments')">
    @if (isDemo())
    <div class="px-4">
        <div class="alert alert-warning">
            <strong>{{  __tr('Demo Alert:') }}</strong>
            {{  __tr('Please note this is addon feature') }}
        </div>
    </div>
    @endif
    <div class="px-3">
        <div id="lwAddonViewPaymentListBody" class="lw-form-modal-body"></div>
        <script type="text/template" id="lwAddonViewPaymentListBody-template">
            <div class="mt-2">
                <% if(!_.isEmpty(__tData.__data?.contact_metadata?.payments)) { %>
                    <%
                        var paymentData = _.orderBy(
                            _.values(__tData.__data?.contact_metadata?.payments || {}),
                            ['paid_at'],
                            ['desc']
                        );
                    %>
                    <% _.forEach(paymentData, function(item) { %>
                        <dt>{{  __tr('Payment Details') }}</dt>
                        <dl class="mb-4">
                            <dd><%- item.order_id %></dd>
                            <dd><%- item.formatted_amount %></dd>
                            <dd><%- item.formatted_paid_at %></dd>
                            <hr class="mt-4">
                        </dl>
                    <% }) %>
                <% } else { %>
                    {{ __tr('There are no payments found.') }}                
                <% } %>
            </div>
        </script>
    </div>
</x-lw.modal>
{{-- Show Payment List Model --}}
@endpush
@endif