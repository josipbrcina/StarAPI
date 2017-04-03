<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<!-- set font style for PDF generator -->
@if (isset($pdf))
<style>
    body {
        font-family: "DejaVu Sans", "sans-serif";
    }
</style>
@endif
<div style="width: 100%">
    <div style="padding: 20px;">
        <img style="width: 200px; height:auto;" src="http://the-shop.io/wp-content/uploads/2015/12/the-shop-logo.png"/>
    </div>
    <div style="font-size: 14px;">
        @yield('content')
    </div>
</div>
