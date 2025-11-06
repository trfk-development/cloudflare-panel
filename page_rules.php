<?php
require_once 'header.php';
if (!isset($_SESSION['user_id'])) { header('Location: ' . BASE_PATH . 'login.php'); exit; }
include 'sidebar.php';
?>
<div class="content p-3">
  <h5><i class="fas fa-scroll me-2"></i>Page Rules</h5>
  <div class="alert alert-info">Быстрое применение типовых правил: Cache Everything и HTTPS Redirect.</div>
  <form class="row g-2" onsubmit="return false;">
    <div class="col-auto">
      <input type="number" class="form-control" id="prDomainId" placeholder="ID домена">
    </div>
    <div class="col-auto">
      <button class="btn btn-warning" onclick="applyRule('cache_static')"><i class="fas fa-box me-1"></i>Cache Everything</button>
      <button class="btn btn-primary" onclick="applyRule('redirect_https')"><i class="fas fa-arrow-right-arrow-left me-1"></i>HTTPS Redirect</button>
    </div>
  </form>
</div>
<script>
async function applyRule(rule){
  const id=document.getElementById('prDomainId').value; if(!id) return;
  const form=new URLSearchParams(); form.append('domain_id',String(id)); form.append('rule_type',rule);
  const r=await fetch('page_rules_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:form.toString()});
  const j=await r.json();
  alert(j.success? 'Правило применено' : ('Ошибка: '+(j.error||'unknown')));
}
</script>
<?php include 'footer.php'; ?> 