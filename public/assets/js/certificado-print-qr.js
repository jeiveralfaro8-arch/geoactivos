(function(){
  var el = document.getElementById('js-cert-print-qr');
  if(!el) return;

  var scriptsJson = el.dataset.scripts || '[]';
  var verifyUrl = el.dataset.verifyUrl || '';

  var scripts = [];
  try { scripts = JSON.parse(scriptsJson); } catch(e){ scripts = []; }

  function loadOne(i, cb){
    if(i >= scripts.length) return cb(false);
    var s = document.createElement('script');
    s.src = scripts[i] + (scripts[i].indexOf('http')===0 ? '' : ('?v=' + Date.now()));
    s.async = true;
    s.onload = function(){ cb(true); };
    s.onerror = function(){ loadOne(i+1, cb); };
    document.head.appendChild(s);
  }

  function renderQR(){
    var host = document.getElementById('qr');
    if(!host) return;
    host.innerHTML = "";

    var text = verifyUrl;
    var size = 200;

    function fallbackMsg(msg){
      host.innerHTML = "<div style='font-size:12px;color:#64748b;text-align:center;max-width:180px;'>" + msg + "</div>";
    }

    try{
      if (typeof QRCode === "undefined"){
        fallbackMsg("No se pudo cargar la librería QR.");
        return;
      }

      new QRCode(host, {
        text: text,
        width: size,
        height: size,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });

      setTimeout(function(){
        var canvas = host.querySelector("canvas");
        if(canvas){
          try{
            var img = document.createElement("img");
            img.alt = "QR verificación";
            img.src = canvas.toDataURL("image/png");
            host.innerHTML = "";
            host.appendChild(img);
          }catch(e){}
        }
      }, 80);

    }catch(e){
      fallbackMsg("Error generando QR.");
    }
  }

  loadOne(0, function(ok){
    renderQR();
  });
})();
