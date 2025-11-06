<?php
require_once 'header.php';
if (!isset($_SESSION['user_id'])) { header('Location: ' . BASE_PATH . 'login.php'); exit; }
include 'sidebar.php';
?>
<div class="content p-3">
  <h5><i class="fas fa-broom me-2"></i>Cache Tools</h5>
  <form class="row g-2" onsubmit="return false;">
    <div class="col-auto">
      <input type="number" class="form-control" id="cacheDomainId" placeholder="ID домена">
    </div>
    <div class="col-auto">
      <button class="btn btn-success" onclick="purgeAll()"><i class="fas fa-broom me-1"></i>Clear Cache (Everything)</button>
    </div>
  </form>
</div>
<script>
async function purgeAll(){
  const id=document.getElementById('cacheDomainId').value; if(!id) return;
  const form=new URLSearchParams(); form.append('domain_id',String(id)); form.append('purge_everything','1');
  const r=await fetch('cache_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:form.toString()});
  const j=await r.json();
  alert(j.success? 'Кеш очищен' : ('Ошибка: '+(j.error||'unknown')));
}
</script>
<?php include 'footer.php'; ?> 