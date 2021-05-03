@extends('layouts.main')

@section('content')
    <div class="row support">
        <div class="col-sm-2 offset-8 mb-3 px-0">
            <div class="btn-group float-end" role="group">
                <button type="button" class="btn btn-sm btn-outline-light">
                    <a href="https://support.findologic.com" target="_blank">Support</a>
                </button>

                <button type="button" class="btn btn-sm btn-outline-light">
                    <a href="https://docs.findologic.com/doku.php?id=integration_documentation:plugin:en:direct_integration:bigcommerce"
                       target="_blank"
                    >Documentation</a>
                </button>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="findologo mt-5 mb-3">
            <a href="https://www.findologic.com/en/" target="_blank">
                <img src="/img/findologic.png" alt="Findologic" />
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-sm-8 form-box shadow mb-5 p-5 bg-body rounded">
            <form method="post" id="config-form" action="/config">

                <input type="hidden" name="store_hash" value="{{ app('request')->session()->get('store_hash') }}">
                <input type="hidden" name="access_token" value="{{ app('request')->session()->get('access_token') }}">
                <input type="hidden" name="context" value="{{ app('request')->session()->get('context') }}">
                <div class="mb-4 row">
                    <label for="shopkey" class="col-sm-3 col-md-2 col-form-label">Shop key</label>
                    <div class="col-sm-9 col-md-10">
                        <input name="shopkey"
                               class="form-control"
                               aria-describedby="shopkeyHelp"
                               value="@isset($shopkey){{ $shopkey }}@endisset"
                        >
                        @empty($shopkey)
                            <div id="shopkeyHelp" class="form-text">
                                Please <a href="https://www.findologic.com/en/contact/" target="_blank">contact us</a>
                                if you haven't received a shop key.
                            </div>
                        @endempty
                    </div>
                </div>
                <div class="row mb-4 form-switch">
                    <label class="col-sm-3 col-md-2 form-check-label" for="fl-status">Active</label>
                    <div class="col-sm-9 col-md-10">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="fl-status"
                            name="active_status"
                            @isset($active_status){{ 'checked' }}@endisset
                        >
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <button type="submit" class="btn btn-primary float-end">
                            <span class="spinner-border loader-wrapper text-light float-end" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </span>
                            <span class="save-text is-active">Save</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(app('request')->session()->get('saved'))
        <div class="toast float-end" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <img src="/img/findologic_square_small.png" class="rounded me-2" alt="Findologic">
                <strong class="me-auto">Findologic</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Configuration saved.
            </div>
        </div>
        <script type="module">
            const toast = new bootstrap.Toast(document.querySelector('.toast'));
            toast.show();
        </script>
    @endif
@stop

