(function(){
  function genHex(len){
    var chars = "0123456789ABCDEF";
    var out = "";
    for(var i=0;i<len;i++){
      out += chars.charAt(Math.floor(Math.random()*chars.length));
    }
    return out;
  }

  var btn = document.getElementById('btn-gen-token');
  var inp = document.getElementById('token_verificacion');
  if(btn && inp){
    btn.addEventListener('click', function(){
      var v = (inp.value || '').trim();
      if(v !== '' && !confirm('¿Reemplazar el token actual?')) return;
      inp.value = 'TOK-' + genHex(18);
      inp.focus();
    });
  }

  var estado = document.getElementById('estado');
  var hint = document.getElementById('hint-estado');
  function updateHint(){
    if(!estado || !hint) return;
    var v = (estado.value || '').toUpperCase();
    if (v === 'CERRADA') hint.innerHTML = 'Sugerido: asignar <b>Número de certificado</b> y <b>Resultado global</b>.';
    else if (v === 'EN_PROCESO') hint.innerHTML = 'Puedes dejar número vacío mientras se ejecuta la calibración.';
    else if (v === 'ANULADA') hint.innerHTML = 'Si anulas, conserva observaciones explicando el motivo.';
    else hint.innerHTML = 'PROGRAMADA: define fechas y condiciones antes de iniciar.';
  }
  if(estado){ estado.addEventListener('change', updateHint); updateHint(); }
})();
