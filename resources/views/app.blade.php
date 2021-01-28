<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Findologic</title>
    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;600&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.1/css/bulma.min.css">
    <link rel="stylesheet" type="text/css" href="/css/custom.css"/>
</head>
<body>

<section class="section">
    <div class="container">
        @if(app('request')->session()->get('saved'))
            <div class="notification is-success">
                <button class="delete"></button>
                Configuration saved.
            </div>
        @endif

        <h1 class="title">
            <img src="/img/findologic.png" alt="Findologic" />
        </h1>

        <div class="box">
            <div class="loader-wrapper">
                <div class="loader is-loading"></div>
            </div>

            <form method="post" action="/config">
                <input type="hidden" name="store_hash" value="{{ app('request')->session()->get('store_hash') }}">
                <input type="hidden" name="access_token" value="{{ app('request')->session()->get('access_token') }}">
                <input type="hidden" name="context" value="{{ app('request')->session()->get('context') }}">

                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input id="fl-status" name="active_status" type="checkbox"
                                   @isset($active_status){{ 'checked' }}@endisset >
                            <label class="label checkbox-active">Active</label>
                        </label>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Shopkey</label>
                    <div class="control">
                        <input id="fl-shopkey" name="shopkey" class="input" type="text" placeholder="Shopkey"
                               value="@isset($shopkey){{ $shopkey }}@endisset">
                    </div>
                </div>

                <div class="control save">
                    <button type="submit" class="button is-bigcommerce">Save</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        (document.querySelectorAll('.notification .delete') || []).forEach(($delete) => {
            var $notification = $delete.parentNode;

            $delete.addEventListener('click', () => {
                $notification.parentNode.removeChild($notification);
            });
        });
    });

    document.querySelector('.is-bigcommerce').addEventListener("click", function () {
        document.querySelector('.loader-wrapper').classList.add('is-active');
    });
</script>
</body>

</html>
