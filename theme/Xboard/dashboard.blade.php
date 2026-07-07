<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no" />
  <title>{{$title}}</title>
  <script type="module" crossorigin src="/theme/{{$theme}}/assets/umi.js"></script>
</head>

<body>

  <script>
    window.routerBase = "/";
    window.settings = {
      title: '{{$title}}',
      assets_path: '/theme/{{$theme}}/assets',
      theme: {
        color: '{{ $theme_config['theme_color'] ?? "default" }}',
      },
      version: '{{$version}}',
      background_url: '{{$theme_config['background_url']}}',
      description: '{{$description}}',
      i18n: [
        'zh-CN',
        'en-US',
        'ja-JP',
        'vi-VN',
        'ko-KR',
        'zh-TW',
        'fa-IR'
      ],
      logo: '{{$logo}}'
    }
  </script>
  <style>
    :root {
      --apt-chatwoot-blue: #2563eb;
      --apt-chatwoot-bottom: calc(112px + env(safe-area-inset-bottom, 0px));
    }

    .woot-widget-bubble,
    .woot-widget-holder,
    #chatwoot_live_chat_widget {
      bottom: var(--apt-chatwoot-bottom) !important;
    }

    .woot-widget-bubble {
      background: var(--apt-chatwoot-blue) !important;
      box-shadow: 0 16px 34px rgba(37, 99, 235, 0.34) !important;
    }
  </style>
  <div id="app"></div>
  {!! $theme_config['custom_html'] !!}
  <!-- Chatwoot customer support widget: managed by BaoTa-visible XBoard theme file. -->
  <script>
    (function(d,t) {
      var BASE_URL = "https://chatwoot.example.com";
      var g = d.createElement(t), s = d.getElementsByTagName(t)[0];
      g.src = BASE_URL + "/packs/js/sdk.js";
      g.async = true;
      s.parentNode.insertBefore(g, s);
      g.onload = function() {
        window.chatwootSDK.run({
          websiteToken: "CHATWOOT_WEBSITE_TOKEN",
          baseUrl: BASE_URL
        });
      };
    })(document, "script");
  </script>
</body>

</html>
