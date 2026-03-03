(function(){
  var el = document.getElementById('qrcode');
  if(!el) return;
  var text = el.dataset.qrText || '';
  if(!text) return;
  el.innerHTML = '';
  new QRCode(el, {
    text: text,
    width: 180,
    height: 180,
    correctLevel: QRCode.CorrectLevel.M
  });
})();
