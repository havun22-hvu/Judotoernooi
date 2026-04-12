<!DOCTYPE html>
<html>
<head>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #111827; }
        iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
    <iframe id="scoreboard" src="about:blank"></iframe>

    <script src="//www.gstatic.com/cast/sdk/libs/caf_receiver/v3/cast_receiver_framework.js"></script>
    <script @nonce>
    const context = cast.framework.CastReceiverContext.getInstance();
    const ns = 'urn:x-cast:judotoernooi';

    context.addCustomMessageListener(ns, function(event) {
        if (event.data && event.data.url) {
            document.getElementById('scoreboard').src = event.data.url;
        }
    });

    context.start();
    </script>
</body>
</html>
