(function(){
  var cb = document.getElementById('usa_dhcp');
  var ip = document.getElementById('ip_fija');
  function sync(){
    if(!cb || !ip) return;
    if(cb.checked){
      ip.setAttribute('readonly','readonly');
      ip.value = '';
    }else{
      ip.removeAttribute('readonly');
    }
  }
  if(cb){ cb.addEventListener('change', sync); sync(); }
})();
