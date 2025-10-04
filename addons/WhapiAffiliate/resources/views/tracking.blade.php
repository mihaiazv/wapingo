<script src="https://parteneri.whapi.ro/integration/general_integration"></script>
<script>
    (function () {
        var tracking = new Affiliates();
        tracking.orderId = @json($orderId);
        tracking.orderAmount = @json($orderAmount);
        tracking.orderCurrency = @json($orderCurrency);
        tracking.orderStatus = @json($orderStatus);
        tracking.orderTracking = @json($orderTracking);
        tracking.orderCustom = @json($customData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        tracking.track();
    })();
</script>
