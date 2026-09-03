<?php
// Admin page views. Included from admin/index.php within <main class="main-content">.
// Available: $page, $settings, $theme, helper functions.
$users = loadJSON('users.json');
$products = loadJSON('products.json');
$categories = loadJSON('categories.json');
$services = loadJSON('services.json');
$invoices = loadJSON('invoices.json');
$tickets = loadJSON('tickets.json');
$orders = loadJSON('orders.json');

function userName($users, $id) {
    foreach ($users as $u) { if ($u['id'] === $id) return $u['name'] . ' (' . $u['email'] . ')'; }
    return 'Unknown';
}
?>

<?php if ($page === 'dashboard'): ?>
<div class="page-header"><div><h1 class="page-title">Dashboard</h1><p class="page-description">Overview of <?php echo htmlspecialchars($settings['site_name']); ?></p></div></div>
<div class="dashboard-cards">
    <div class="dashboard-card"><div class="dashboard-card-value"><?php echo count($users); ?></div><div class="dashboard-card-label">Customers</div></div>
    <div class="dashboard-card"><div class="dashboard-card-value"><?php echo count(array_filter($services, fn($s)=>$s['status']==='active')); ?></div><div class="dashboard-card-label">Active Services</div></div>
    <div class="dashboard-card"><div class="dashboard-card-value"><?php echo count(array_filter($invoices, fn($i)=>$i['status']==='unpaid')); ?></div><div class="dashboard-card-label">Unpaid Invoices</div></div>
    <div class="dashboard-card"><div class="dashboard-card-value"><?php echo count(array_filter($tickets, fn($t)=>$t['status']==='open')); ?></div><div class="dashboard-card-label">Open Tickets</div></div>
</div>
<div class="card">
    <h3 class="card-title">Orders awaiting follow-up</h3>
    <?php $pendingFollow = array_filter($orders, fn($o)=>empty($o['followup_sent'])); ?>
    <?php if (empty($pendingFollow)): ?>
    <p class="text-muted mt-3">No pending follow-ups.</p>
    <?php else: ?>
    <div class="table-container mt-3"><table class="table"><thead><tr><th>Customer</th><th>Product</th><th>Placed</th><th>Follow-up due</th><th></th></tr></thead><tbody>
        <?php foreach (array_slice(array_reverse($pendingFollow),0,5) as $o): ?>
        <tr>
            <td><?php echo htmlspecialchars(userName($users,$o['user_id'])); ?></td>
            <td><?php echo htmlspecialchars($o['product_name']); ?></td>
            <td><?php echo htmlspecialchars($o['created_at']); ?></td>
            <td><?php echo htmlspecialchars($o['followup_due'] ?? '-'); ?></td>
            <td><a href="?action=send_followup&id=<?php echo $o['id']; ?>" class="btn btn-sm btn-primary">Send follow-up</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<?php elseif ($page === 'products'): ?>
<div class="page-header"><div><h1 class="page-title">Products</h1></div><a href="?page=product" class="btn btn-primary">New Product</a></div>
<div class="table-container"><table class="table"><thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($products as $p): ?>
    <tr>
        <td><?php echo htmlspecialchars($p['name']); ?></td>
        <td><?php echo htmlspecialchars($p['category']); ?></td>
        <td><?php echo !empty($p['is_quote']) ? 'Quote' : formatPrice($p['price']); ?></td>
        <td><span class="badge badge-<?php echo $p['enabled']?'success':'secondary'; ?>"><?php echo $p['enabled']?'Enabled':'Disabled'; ?></span></td>
        <td>
            <a href="?page=product&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
            <a href="?action=delete_product&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody></table></div>

<?php elseif ($page === 'product'): ?>
<?php
$editId = $_GET['id'] ?? '';
$edit = null;
foreach ($products as $p) { if ($p['id'] === $editId) { $edit = $p; break; } }
?>
<div class="page-header"><div><h1 class="page-title"><?php echo $edit ? 'Edit Product' : 'New Product'; ?></h1></div><a href="?page=products" class="btn btn-outline">&larr; Back</a></div>
<div class="card" style="max-width:700px;">
    <form method="POST" action="?action=save_product">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit['id'] ?? ''); ?>">
        <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">Category</label><select name="category" class="form-select"><?php foreach ($categories as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo ($edit['category'] ?? '')===$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" class="form-input" value="<?php echo htmlspecialchars($edit['description'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-input" value="<?php echo htmlspecialchars($edit['price'] ?? '0'); ?>"></div>
        <div class="form-group"><label class="form-label">Billing cycle</label><select name="billing_cycle" class="form-select"><option value="monthly" <?php echo ($edit['billing_cycle'] ?? '')==='monthly'?'selected':''; ?>>Monthly</option><option value="yearly" <?php echo ($edit['billing_cycle'] ?? '')==='yearly'?'selected':''; ?>>Yearly</option><option value="once" <?php echo ($edit['billing_cycle'] ?? '')==='once'?'selected':''; ?>>One-time</option></select></div>
        <div class="form-group"><label class="form-label">Features (one per line)</label><textarea name="features" class="form-textarea" rows="5"><?php echo htmlspecialchars(implode("\n", $edit['features'] ?? [])); ?></textarea></div>
        <div class="form-group"><label class="form-label">Stripe Price ID (optional)</label><input type="text" name="stripe_price_id" class="form-input" value="<?php echo htmlspecialchars($edit['stripe_price_id'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">KeyHelp Package/Plan ID (optional)</label><input type="text" name="keyhelp_package_id" class="form-input" value="<?php echo htmlspecialchars($edit['keyhelp_package_id'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Included IPv4 (VPS)</label><input type="number" name="included_ipv4" class="form-input" value="<?php echo htmlspecialchars($edit['included_ipv4'] ?? '0'); ?>"></div>
        <div class="form-group"><label><input type="checkbox" name="is_vps" <?php echo !empty($edit['is_vps'])?'checked':''; ?>> This is a VPS (enables extra IPv4 ordering)</label></div>
        <div class="form-group"><label><input type="checkbox" name="is_quote" <?php echo !empty($edit['is_quote'])?'checked':''; ?>> Quote-based (opens web-dev ticket instead of checkout)</label></div>
        <div class="form-group"><label><input type="checkbox" name="enabled" <?php echo (!isset($edit['enabled']) || $edit['enabled'])?'checked':''; ?>> Enabled</label></div>
        <button type="submit" class="btn btn-primary">Save Product</button>
    </form>
</div>

<?php elseif ($page === 'categories'): ?>
<div class="page-header"><div><h1 class="page-title">Categories</h1></div></div>
<div class="card" style="max-width:600px;">
    <form method="POST" action="?action=save_category">
        <div class="form-group"><label class="form-label">ID (slug)</label><input type="text" name="cat_id" class="form-input" placeholder="e.g. vps" required></div>
        <div class="form-group"><label class="form-label">Name</label><input type="text" name="cat_name" class="form-input" required></div>
        <div class="form-group"><label class="form-label">Icon</label><input type="text" name="cat_icon" class="form-input" placeholder="server"></div>
        <button type="submit" class="btn btn-primary">Add / Update Category</button>
    </form>
</div>
<div class="table-container mt-4"><table class="table"><thead><tr><th>ID</th><th>Name</th><th></th></tr></thead><tbody>
    <?php foreach ($categories as $c): ?>
    <tr><td><?php echo htmlspecialchars($c['id']); ?></td><td><?php echo htmlspecialchars($c['name']); ?></td><td><a href="?action=delete_category&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete category?')">Delete</a></td></tr>
    <?php endforeach; ?>
</tbody></table></div>

<?php elseif ($page === 'users'): ?>
<div class="page-header"><div><h1 class="page-title">Customers</h1></div></div>
<div class="table-container"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Registered</th><th>Services</th><th></th></tr></thead><tbody>
    <?php foreach ($users as $u): $svcCount = count(array_filter($services, fn($s)=>$s['user_id']===$u['id'])); ?>
    <tr>
        <td><?php echo htmlspecialchars($u['name']); ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><?php echo htmlspecialchars($u['created_at']); ?></td>
        <td><?php echo $svcCount; ?></td>
        <td><a href="?page=user&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline">Manage</a></td>
    </tr>
    <?php endforeach; ?>
</tbody></table></div>

<?php elseif ($page === 'user'): ?>
<?php
$uid = $_GET['id'] ?? '';
$user = null;
foreach ($users as $u) { if ($u['id'] === $uid) { $user = $u; break; } }
?>
<?php if (!$user): ?>
<div class="alert alert-danger">Customer not found.</div>
<?php else: ?>
<div class="page-header"><div><h1 class="page-title"><?php echo htmlspecialchars($user['name']); ?></h1><p class="page-description"><?php echo htmlspecialchars($user['email']); ?></p></div><a href="?page=users" class="btn btn-outline">&larr; Back</a></div>

<div class="card">
    <h3 class="card-title">Services</h3>
    <?php $userSvc = array_values(array_filter($services, fn($s)=>$s['user_id']===$uid)); ?>
    <?php if (empty($userSvc)): ?><p class="text-muted mt-3">No services.</p><?php else: ?>
    <div class="table-container mt-3"><table class="table"><thead><tr><th>Service</th><th>Price</th><th>Status</th><th>Next due</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($userSvc as $s): $ret = urlencode('?page=user&id='.$uid); ?>
        <tr>
            <td><?php echo htmlspecialchars($s['product_name']); ?><?php echo !empty($s['extra_ipv4']) ? ' <span class="badge badge-secondary">'.(int)$s['extra_ipv4'].' IPv4</span>' : ''; ?></td>
            <td><?php echo formatPrice($s['price']); ?></td>
            <td><span class="badge badge-<?php echo $s['status']==='active'?'success':($s['status']==='suspended'?'warning':'danger'); ?>"><?php echo ucfirst($s['status']); ?></span></td>
            <td><?php echo htmlspecialchars($s['next_due_date'] ?? '-'); ?></td>
            <td>
                <?php if ($s['status'] !== 'active'): ?><a href="?action=service_activate&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-success">Activate</a><?php endif; ?>
                <?php if ($s['status'] === 'active'): ?><a href="?action=service_suspend&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-warning">Suspend</a><?php endif; ?>
                <a href="?action=service_renew&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-outline">Renew</a>
                <a href="?action=service_terminate&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Terminate service?')">Terminate</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<div class="card mt-4">
    <h3 class="card-title">Link a service to this customer</h3>
    <form method="POST" action="?action=link_service" class="mt-3">
        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
        <div class="form-group"><label class="form-label">Product</label><select name="product_id" class="form-select"><?php foreach ($products as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label class="form-label">Price override (optional)</label><input type="number" step="0.01" name="price" class="form-input" placeholder="Uses product price if empty"></div>
        <div class="form-group"><label class="form-label">KeyHelp Client ID (optional, to link existing account)</label><input type="text" name="keyhelp_client_id" class="form-input"></div>
        <button type="submit" class="btn btn-primary">Link Service</button>
    </form>
</div>

<div class="card mt-4">
    <h3 class="card-title">Create invoice</h3>
    <form method="POST" action="?action=create_invoice" class="mt-3">
        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
        <input type="hidden" name="return" value="?page=user&id=<?php echo $uid; ?>">
        <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" class="form-input" required></div>
        <div class="form-group"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-input" required></div>
        <button type="submit" class="btn btn-primary">Create Invoice</button>
    </form>
</div>

<div class="card mt-4">
    <h3 class="card-title">Reset password</h3>
    <form method="POST" action="?action=reset_user_password" class="mt-3">
        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
        <div class="form-group"><label class="form-label">New password (min 6 chars)</label><input type="password" name="new_password" class="form-input" minlength="6" required></div>
        <button type="submit" class="btn btn-danger">Reset Password</button>
    </form>
</div>
<?php endif; ?>

<?php elseif ($page === 'services'): ?>
<div class="page-header"><div><h1 class="page-title">All Services</h1></div></div>
<div class="table-container"><table class="table"><thead><tr><th>Customer</th><th>Service</th><th>Price</th><th>Status</th><th>Next due</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($services as $s): $ret = urlencode('?page=services'); ?>
    <tr>
        <td><?php echo htmlspecialchars(userName($users,$s['user_id'])); ?></td>
        <td><?php echo htmlspecialchars($s['product_name']); ?></td>
        <td><?php echo formatPrice($s['price']); ?></td>
        <td><span class="badge badge-<?php echo $s['status']==='active'?'success':($s['status']==='suspended'?'warning':'danger'); ?>"><?php echo ucfirst($s['status']); ?></span></td>
        <td><?php echo htmlspecialchars($s['next_due_date'] ?? '-'); ?></td>
        <td>
            <?php if ($s['status'] !== 'active'): ?><a href="?action=service_activate&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-success">Activate</a><?php endif; ?>
            <?php if ($s['status'] === 'active'): ?><a href="?action=service_suspend&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-warning">Suspend</a><?php endif; ?>
            <a href="?action=service_renew&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-outline">Renew</a>
            <a href="?action=service_terminate&id=<?php echo $s['id']; ?>&return=<?php echo $ret; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Terminate?')">Terminate</a>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody></table></div>

<?php elseif ($page === 'orders'): ?>
<div class="page-header"><div><h1 class="page-title">Orders &amp; Follow-up</h1><p class="page-description">Send the 24 working-hour follow-up email manually</p></div></div>
<div class="table-container"><table class="table"><thead><tr><th>Customer</th><th>Product</th><th>Total</th><th>Placed</th><th>Follow-up due</th><th>Follow-up</th></tr></thead><tbody>
    <?php foreach (array_reverse($orders) as $o): ?>
    <tr>
        <td><?php echo htmlspecialchars(userName($users,$o['user_id'])); ?></td>
        <td><?php echo htmlspecialchars($o['product_name']); ?></td>
        <td><?php echo formatPrice($o['total']); ?></td>
        <td><?php echo htmlspecialchars($o['created_at']); ?></td>
        <td><?php echo htmlspecialchars($o['followup_due'] ?? '-'); ?></td>
        <td>
            <?php if (!empty($o['followup_sent'])): ?>
            <span class="badge badge-success">Sent <?php echo htmlspecialchars($o['followup_sent_at'] ?? ''); ?></span>
            <?php else: ?>
            <a href="?action=send_followup&id=<?php echo $o['id']; ?>" class="btn btn-sm btn-primary">Send follow-up</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?><tr><td colspan="6" class="text-muted">No orders yet.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($page === 'invoices'): ?>
<div class="page-header"><div><h1 class="page-title">Invoices</h1></div></div>
<div class="table-container"><table class="table"><thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
    <?php foreach (array_reverse($invoices) as $inv): ?>
    <tr>
        <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
        <td><?php echo htmlspecialchars(userName($users,$inv['user_id'])); ?></td>
        <td><?php echo formatPrice($inv['total']); ?></td>
        <td><span class="badge badge-<?php echo $inv['status']==='paid'?'success':'warning'; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
        <td><?php echo htmlspecialchars($inv['created_at']); ?></td>
        <td><a href="?action=toggle_invoice_paid&id=<?php echo $inv['id']; ?>&return=<?php echo urlencode('?page=invoices'); ?>" class="btn btn-sm btn-outline">Mark <?php echo $inv['status']==='paid'?'unpaid':'paid'; ?></a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($invoices)): ?><tr><td colspan="6" class="text-muted">No invoices yet.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($page === 'tickets'): ?>
<div class="page-header"><div><h1 class="page-title">Tickets</h1></div></div>
<div class="ticket-list">
    <?php usort($tickets, fn($a,$b)=>strtotime($b['updated_at'])-strtotime($a['updated_at'])); ?>
    <?php foreach ($tickets as $t): ?>
    <a href="?page=ticket&id=<?php echo $t['id']; ?>" class="ticket-item">
        <div class="ticket-info">
            <h4><?php echo htmlspecialchars($t['subject']); ?></h4>
            <div class="ticket-meta"><span><?php echo htmlspecialchars(userName($users,$t['user_id'])); ?></span><span><?php echo htmlspecialchars($t['department'] ?? ''); ?></span><span><?php echo htmlspecialchars($t['updated_at']); ?></span></div>
        </div>
        <span class="badge badge-<?php echo $t['status']==='open'?'success':($t['status']==='in_progress'?'warning':'secondary'); ?>"><?php echo $t['status']==='open'?'Open':($t['status']==='in_progress'?'In progress':'Closed'); ?></span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($tickets)): ?><p class="text-muted">No tickets.</p><?php endif; ?>
</div>

<?php elseif ($page === 'ticket'): ?>
<?php $tid = $_GET['id'] ?? ''; $ticket = null; foreach ($tickets as $t) { if ($t['id']===$tid) { $ticket=$t; break; } } ?>
<?php if (!$ticket): ?><div class="alert alert-danger">Ticket not found.</div><?php else: ?>
<div class="page-header"><div><h1 class="page-title"><?php echo htmlspecialchars($ticket['subject']); ?></h1><p class="page-description">From <?php echo htmlspecialchars(userName($users,$ticket['user_id'])); ?> &middot; <?php echo htmlspecialchars($ticket['department'] ?? ''); ?></p></div><a href="?page=tickets" class="btn btn-outline">&larr; Back</a></div>
<div class="card">
    <div class="ticket-messages">
        <?php foreach ($ticket['messages'] as $m): ?>
        <div class="message <?php echo $m['is_staff']?'message-staff':'message-user'; ?>"><p><?php echo nl2br(htmlspecialchars($m['message'])); ?></p><div class="message-meta"><?php echo htmlspecialchars($m['created_at']); ?></div></div>
        <?php endforeach; ?>
    </div>
    <form method="POST" action="?action=reply_ticket" class="mt-4">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
        <div class="form-group"><label class="form-label">Reply</label><textarea name="message" class="form-textarea" rows="4" required></textarea></div>
        <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-select"><option value="open">Open</option><option value="in_progress" selected>In progress</option><option value="closed">Closed</option></select></div>
        <button type="submit" class="btn btn-primary">Send Reply</button>
    </form>
</div>
<?php endif; ?>

<?php elseif ($page === 'coupons'): ?>
<?php $coupons = loadJSON('coupons.json'); $editC = null; foreach ($coupons as $c) { if ($c['id'] === ($_GET['edit'] ?? '')) { $editC = $c; } } ?>
<div class="page-header"><div><h1 class="page-title">Coupons</h1><p class="page-description">Discount codes for checkout</p></div></div>
<div class="card" style="max-width:600px;">
    <form method="POST" action="?action=save_coupon">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editC['id'] ?? ''); ?>">
        <div class="form-group"><label class="form-label">Code</label><input type="text" name="code" class="form-input" value="<?php echo htmlspecialchars($editC['code'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-select"><option value="percent" <?php echo ($editC['type'] ?? '')==='percent'?'selected':''; ?>>Percentage</option><option value="fixed" <?php echo ($editC['type'] ?? '')==='fixed'?'selected':''; ?>>Fixed amount</option></select></div>
        <div class="form-group"><label class="form-label">Value (% or amount)</label><input type="number" step="0.01" name="value" class="form-input" value="<?php echo htmlspecialchars($editC['value'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">Max uses (0 = unlimited)</label><input type="number" name="max_uses" class="form-input" value="<?php echo htmlspecialchars($editC['max_uses'] ?? '0'); ?>"></div>
        <div class="form-group"><label class="form-label">Expires (optional, YYYY-MM-DD)</label><input type="date" name="expires" class="form-input" value="<?php echo htmlspecialchars($editC['expires'] ?? ''); ?>"></div>
        <div class="form-group"><label><input type="checkbox" name="enabled" <?php echo (!isset($editC['enabled']) || $editC['enabled'])?'checked':''; ?>> Enabled</label></div>
        <button type="submit" class="btn btn-primary">Save Coupon</button>
    </form>
</div>
<div class="table-container mt-4"><table class="table"><thead><tr><th>Code</th><th>Discount</th><th>Uses</th><th>Expires</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($coupons as $c): ?>
    <tr>
        <td><strong><?php echo htmlspecialchars($c['code']); ?></strong></td>
        <td><?php echo $c['type']==='percent' ? ($c['value'].'%') : formatPrice($c['value']); ?></td>
        <td><?php echo ($c['uses'] ?? 0); ?><?php echo !empty($c['max_uses']) ? ' / '.$c['max_uses'] : ''; ?></td>
        <td><?php echo htmlspecialchars($c['expires'] ?: '-'); ?></td>
        <td><span class="badge badge-<?php echo !empty($c['enabled'])?'success':'secondary'; ?>"><?php echo !empty($c['enabled'])?'Active':'Off'; ?></span></td>
        <td><a href="?page=coupons&edit=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline">Edit</a> <a href="?action=delete_coupon&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete coupon?')">Delete</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($coupons)): ?><tr><td colspan="6" class="text-muted">No coupons yet.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($page === 'keyhelp'): ?>
<?php $kh = loadJSON('keyhelp.json'); ?>
<div class="page-header"><div><h1 class="page-title">KeyHelp Integration</h1><p class="page-description">Full account sync with your KeyHelp server</p></div></div>
<div class="card" style="max-width:700px;">
    <form method="POST" action="?action=save_keyhelp">
        <div class="form-group"><label><input type="checkbox" name="enabled" <?php echo !empty($kh['enabled'])?'checked':''; ?>> Enable KeyHelp integration</label></div>
        <div class="form-group"><label class="form-label">API URL (e.g. https://server.example.com)</label><input type="text" name="api_url" class="form-input" value="<?php echo htmlspecialchars($kh['api_url'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">API Key</label><input type="text" name="api_key" class="form-input" value="<?php echo htmlspecialchars($kh['api_key'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Default hosting plan ID</label><input type="text" name="default_package_id" class="form-input" value="<?php echo htmlspecialchars($kh['default_package_id'] ?? ''); ?>"></div>
        <div class="form-group"><label><input type="checkbox" name="auto_create" <?php echo !empty($kh['auto_create'])?'checked':''; ?>> Automatically create hosting account on webhosting orders</label></div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="?action=test_keyhelp" class="btn btn-outline">Test connection</a>
    </form>
</div>
<div class="card mt-4">
    <h3 class="card-title">KeyHelp Accounts</h3>
    <?php if (!keyhelpEnabled()): ?>
    <p class="text-muted mt-3">Enable and configure KeyHelp above to view accounts.</p>
    <?php else: $clients = keyhelpListClients(); ?>
    <div class="table-container mt-3"><table class="table"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($clients as $cl): $cid = $cl['id'] ?? ($cl['username'] ?? ''); ?>
        <tr>
            <td><?php echo htmlspecialchars($cid); ?></td>
            <td><?php echo htmlspecialchars($cl['username'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($cl['email'] ?? ''); ?></td>
            <td><?php echo !empty($cl['is_suspended']) ? '<span class="badge badge-warning">Suspended</span>' : '<span class="badge badge-success">Active</span>'; ?></td>
            <td>
                <?php if (!empty($cl['is_suspended'])): ?><a href="?action=keyhelp_unsuspend&id=<?php echo urlencode($cid); ?>" class="btn btn-sm btn-success">Unsuspend</a><?php else: ?><a href="?action=keyhelp_suspend&id=<?php echo urlencode($cid); ?>" class="btn btn-sm btn-warning">Suspend</a><?php endif; ?>
                <a href="?action=keyhelp_delete&id=<?php echo urlencode($cid); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete KeyHelp account?')">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($clients)): ?><tr><td colspan="5" class="text-muted">No accounts found (or connection failed).</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>

<?php elseif ($page === 'whitelabel'): ?>
<?php $wl = loadJSON('whitelabel.json'); ?>
<div class="page-header"><div><h1 class="page-title">White-label</h1><p class="page-description">Rebrand the platform for resellers</p></div></div>
<div class="card" style="max-width:700px;">
    <form method="POST" action="?action=save_whitelabel">
        <div class="form-group"><label><input type="checkbox" name="enabled" <?php echo !empty($wl['enabled'])?'checked':''; ?>> Enable white-label mode</label></div>
        <div class="form-group"><label><input type="checkbox" name="hide_powered_by" <?php echo !empty($wl['hide_powered_by'])?'checked':''; ?>> Hide "Powered by Profie-IT.nl" footer</label></div>
        <div class="form-group"><label class="form-label">Custom footer text</label><input type="text" name="footer_text" class="form-input" value="<?php echo htmlspecialchars($wl['footer_text'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Custom CSS (applied site-wide)</label><textarea name="custom_css" class="form-textarea" rows="8"><?php echo htmlspecialchars($wl['custom_css'] ?? ''); ?></textarea></div>
        <p class="text-muted" style="font-size:0.85rem;">Tip: change the logo, colors and site name under Theme &amp; Settings to fully rebrand.</p>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>

<?php elseif ($page === 'stripe'): ?>
<?php $stripe = loadJSON('stripe.json'); ?>
<div class="page-header"><div><h1 class="page-title">Stripe</h1><p class="page-description">Online card payments (can be disabled)</p></div></div>
<div class="card" style="max-width:700px;">
    <form method="POST" action="?action=save_stripe">
        <div class="form-group"><label><input type="checkbox" name="enabled" <?php echo !empty($stripe['enabled'])?'checked':''; ?>> Enable Stripe checkout</label></div>
        <div class="form-group"><label class="form-label">Publishable key</label><input type="text" name="public_key" class="form-input" value="<?php echo htmlspecialchars($stripe['public_key'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Secret key</label><input type="text" name="secret_key" class="form-input" value="<?php echo htmlspecialchars($stripe['secret_key'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Webhook secret</label><input type="text" name="webhook_secret" class="form-input" value="<?php echo htmlspecialchars($stripe['webhook_secret'] ?? ''); ?>"></div>
        <p class="text-muted" style="font-size:0.85rem;">When disabled, customers pay via bank transfer only.</p>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>

<?php elseif ($page === 'mail'): ?>
<?php $mail = loadJSON('mailserverconfig.json'); ?>
<div class="page-header"><div><h1 class="page-title">Mail Settings</h1></div></div>
<div class="card" style="max-width:700px;">
    <form method="POST" action="?action=save_mail">
        <div class="form-group"><label class="form-label">SMTP host</label><input type="text" name="smtp_host" class="form-input" value="<?php echo htmlspecialchars($mail['smtp_host'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">SMTP port</label><input type="number" name="smtp_port" class="form-input" value="<?php echo htmlspecialchars($mail['smtp_port'] ?? 587); ?>"></div>
        <div class="form-group"><label class="form-label">SMTP user</label><input type="text" name="smtp_user" class="form-input" value="<?php echo htmlspecialchars($mail['smtp_user'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">SMTP password</label><input type="password" name="smtp_pass" class="form-input" value="<?php echo htmlspecialchars($mail['smtp_pass'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Encryption</label><select name="smtp_secure" class="form-select"><option value="tls" <?php echo ($mail['smtp_secure'] ?? '')==='tls'?'selected':''; ?>>TLS</option><option value="ssl" <?php echo ($mail['smtp_secure'] ?? '')==='ssl'?'selected':''; ?>>SSL</option><option value="none" <?php echo ($mail['smtp_secure'] ?? '')==='none'?'selected':''; ?>>None</option></select></div>
        <div class="form-group"><label class="form-label">From email</label><input type="email" name="from_email" class="form-input" value="<?php echo htmlspecialchars($mail['from_email'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">From name</label><input type="text" name="from_name" class="form-input" value="<?php echo htmlspecialchars($mail['from_name'] ?? ''); ?>"></div>
        <h3 class="mt-4 mb-3">Follow-up email template</h3>
        <p class="text-muted" style="font-size:0.85rem;">Placeholders: {name}, {product}</p>
        <div class="form-group"><label class="form-label">Follow-up subject</label><input type="text" name="followup_subject" class="form-input" value="<?php echo htmlspecialchars($mail['followup_subject'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Follow-up body</label><textarea name="followup_body" class="form-textarea" rows="8"><?php echo htmlspecialchars($mail['followup_body'] ?? ''); ?></textarea></div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>

<?php elseif ($page === 'staff'): ?>
<?php $staff = loadJSON('staff.json'); ?>
<div class="page-header"><div><h1 class="page-title">Staff</h1></div><a href="?page=edit-staff" class="btn btn-primary">New Staff</a></div>
<div class="table-container"><table class="table"><thead><tr><th>Name</th><th>Username</th><th>Permissions</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($staff as $s): ?>
    <tr>
        <td><?php echo htmlspecialchars($s['name']); ?></td>
        <td><?php echo htmlspecialchars($s['username']); ?></td>
        <td><?php echo htmlspecialchars(implode(', ', $s['permissions'] ?? [])); ?></td>
        <td><span class="badge badge-<?php echo !empty($s['enabled'])?'success':'secondary'; ?>"><?php echo !empty($s['enabled'])?'Enabled':'Disabled'; ?></span></td>
        <td><a href="?page=edit-staff&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline">Edit</a> <a href="?action=delete_staff&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete staff?')">Delete</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($staff)): ?><tr><td colspan="5" class="text-muted">No staff members.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($page === 'edit-staff'): ?>
<?php $staff = loadJSON('staff.json'); $editS = null; foreach ($staff as $s) { if ($s['id'] === ($_GET['id'] ?? '')) { $editS = $s; } }
$allPerms = ['products','users','services','invoices','tickets','kb']; ?>
<div class="page-header"><div><h1 class="page-title"><?php echo $editS ? 'Edit Staff' : 'New Staff'; ?></h1></div><a href="?page=staff" class="btn btn-outline">&larr; Back</a></div>
<div class="card" style="max-width:600px;">
    <form method="POST" action="?action=save_staff">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editS['id'] ?? ''); ?>">
        <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($editS['name'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($editS['username'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($editS['email'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Password <?php echo $editS ? '(leave blank to keep)' : ''; ?></label><input type="password" name="password" class="form-input" <?php echo $editS ? '' : 'required'; ?>></div>
        <div class="form-group"><label class="form-label">Permissions</label>
            <?php foreach ($allPerms as $perm): ?>
            <label style="display:block;margin:4px 0;"><input type="checkbox" name="permissions[]" value="<?php echo $perm; ?>" <?php echo in_array($perm, $editS['permissions'] ?? [])?'checked':''; ?>> <?php echo ucfirst($perm); ?></label>
            <?php endforeach; ?>
        </div>
        <div class="form-group"><label><input type="checkbox" name="enabled" <?php echo (!isset($editS['enabled']) || $editS['enabled'])?'checked':''; ?>> Enabled</label></div>
        <button type="submit" class="btn btn-primary">Save Staff</button>
    </form>
</div>

<?php elseif ($page === 'settings'): ?>
<div class="page-header"><div><h1 class="page-title">General Settings</h1></div></div>
<div class="card" style="max-width:700px;">
    <form method="POST" action="?action=save_settings">
        <div class="form-group"><label class="form-label">Site name</label><input type="text" name="site_name" class="form-input" value="<?php echo htmlspecialchars($settings['site_name']); ?>"></div>
        <div class="form-group"><label class="form-label">Site description</label><input type="text" name="site_description" class="form-input" value="<?php echo htmlspecialchars($settings['site_description']); ?>"></div>
        <div class="form-group"><label class="form-label">Company email</label><input type="email" name="company_email" class="form-input" value="<?php echo htmlspecialchars($settings['company_email']); ?>"></div>
        <div class="form-group"><label class="form-label">Company phone</label><input type="text" name="company_phone" class="form-input" value="<?php echo htmlspecialchars($settings['company_phone']); ?>"></div>
        <div class="form-group"><label class="form-label">Extra IPv4 price (per address)</label><input type="number" step="0.01" name="ipv4_price" class="form-input" value="<?php echo htmlspecialchars($settings['ipv4_price'] ?? '1.50'); ?>"></div>

        <h3 class="mt-4 mb-3">Homepage Stats</h3>
        <div id="statsContainer">
            <?php foreach ($settings['stats'] ?? [] as $stat): ?>
            <div class="form-row" style="display:flex;gap:8px;margin-bottom:8px;">
                <input type="text" name="stat_value[]" class="form-input" value="<?php echo htmlspecialchars($stat['value']); ?>" placeholder="99.9%">
                <input type="text" name="stat_label[]" class="form-input" value="<?php echo htmlspecialchars($stat['label']); ?>" placeholder="Uptime">
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline" onclick="addStat()">+ Add stat</button>

        <h3 class="mt-4 mb-3">Partners</h3>
        <div id="partnersContainer">
            <?php foreach ($settings['partners'] ?? [] as $partner): ?>
            <div class="form-row" style="display:flex;gap:8px;margin-bottom:8px;">
                <input type="text" name="partner_name[]" class="form-input" value="<?php echo htmlspecialchars($partner['name']); ?>" placeholder="AS205838">
                <input type="text" name="partner_desc[]" class="form-input" value="<?php echo htmlspecialchars($partner['description'] ?? ''); ?>" placeholder="Description">
                <input type="text" name="partner_url[]" class="form-input" value="<?php echo htmlspecialchars($partner['url'] ?? ''); ?>" placeholder="https://">
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline" onclick="addPartner()">+ Add partner</button>

        <div class="mt-4"><button type="submit" class="btn btn-primary">Save Settings</button></div>
    </form>
</div>
<script>
function addStat(){document.getElementById('statsContainer').insertAdjacentHTML('beforeend','<div class="form-row" style="display:flex;gap:8px;margin-bottom:8px;"><input type="text" name="stat_value[]" class="form-input" placeholder="Value"><input type="text" name="stat_label[]" class="form-input" placeholder="Label"></div>');}
function addPartner(){document.getElementById('partnersContainer').insertAdjacentHTML('beforeend','<div class="form-row" style="display:flex;gap:8px;margin-bottom:8px;"><input type="text" name="partner_name[]" class="form-input" placeholder="Name"><input type="text" name="partner_desc[]" class="form-input" placeholder="Description"><input type="text" name="partner_url[]" class="form-input" placeholder="https://"></div>');}
</script>

<?php elseif ($page === 'theme'): ?>
<div class="page-header"><div><h1 class="page-title">Theme</h1><p class="page-description">Customize colors, logo and fonts</p></div></div>
<div class="card" style="max-width:700px;">
    <form method="POST" action="?action=save_theme">
        <div class="form-group"><label class="form-label">Primary color</label><input type="color" name="primary_color" class="form-input" value="<?php echo htmlspecialchars($theme['primary_color'] ?? '#2563eb'); ?>"></div>
        <div class="form-group"><label class="form-label">Secondary color</label><input type="color" name="secondary_color" class="form-input" value="<?php echo htmlspecialchars($theme['secondary_color'] ?? '#0ea5e9'); ?>"></div>
        <div class="form-group"><label class="form-label">Background color</label><input type="color" name="background_color" class="form-input" value="<?php echo htmlspecialchars($theme['background_color'] ?? '#0f172a'); ?>"></div>
        <div class="form-group"><label class="form-label">Card color</label><input type="color" name="card_color" class="form-input" value="<?php echo htmlspecialchars($theme['card_color'] ?? '#1e293b'); ?>"></div>
        <div class="form-group"><label class="form-label">Text color</label><input type="color" name="text_color" class="form-input" value="<?php echo htmlspecialchars($theme['text_color'] ?? '#f8fafc'); ?>"></div>
        <div class="form-group"><label class="form-label">Muted text color</label><input type="color" name="muted_color" class="form-input" value="<?php echo htmlspecialchars($theme['muted_color'] ?? '#94a3b8'); ?>"></div>
        <div class="form-group"><label class="form-label">Logo path</label><input type="text" name="logo" class="form-input" value="<?php echo htmlspecialchars($theme['logo'] ?? 'logo.png'); ?>"></div>
        <div class="form-group"><label class="form-label">Favicon path</label><input type="text" name="favicon" class="form-input" value="<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>"></div>
        <div class="form-group"><label class="form-label">Font family</label><input type="text" name="font_family" class="form-input" value="<?php echo htmlspecialchars($theme['font_family'] ?? 'Inter'); ?>"></div>
        <div class="form-group"><label class="form-label">404 message</label><input type="text" name="error_404" class="form-input" value="<?php echo htmlspecialchars($theme['error_404'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">403 message</label><input type="text" name="error_403" class="form-input" value="<?php echo htmlspecialchars($theme['error_403'] ?? ''); ?>"></div>
        <button type="submit" class="btn btn-primary">Save Theme</button>
    </form>
</div>

<?php elseif ($page === 'password'): ?>
<?php $auth = loadJSON('auth.json'); ?>
<div class="page-header"><div><h1 class="page-title">Change Login</h1></div></div>
<div class="card" style="max-width:600px;">
    <form method="POST" action="?action=change_password">
        <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($auth['username']); ?>"></div>
        <div class="form-group"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-input" required></div>
        <div class="form-group"><label class="form-label">New password (leave blank to keep)</label><input type="password" name="new_password" class="form-input"></div>
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
</div>

<?php elseif ($page === 'logs'): ?>
<?php $logs = array_reverse(loadJSON('logs.json')); ?>
<div class="page-header"><div><h1 class="page-title">Activity Logs</h1></div></div>
<div class="table-container"><table class="table"><thead><tr><th>Time</th><th>Action</th><th>IP</th></tr></thead><tbody>
    <?php foreach (array_slice($logs,0,200) as $log): ?>
    <tr><td><?php echo htmlspecialchars($log['timestamp']); ?></td><td><?php echo htmlspecialchars($log['action']); ?></td><td><?php echo htmlspecialchars($log['ip']); ?></td></tr>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?><tr><td colspan="3" class="text-muted">No logs.</td></tr><?php endif; ?>
</tbody></table></div>

<?php elseif ($page === 'kb'): ?>
<?php $kb = loadJSON('kb.json'); ?>
<div class="page-header"><div><h1 class="page-title">Knowledge Base</h1></div><a href="?page=article" class="btn btn-primary">New Article</a></div>
<div class="table-container"><table class="table"><thead><tr><th>Title</th><th>Category</th><th></th></tr></thead><tbody>
    <?php foreach ($kb['articles'] as $a): ?>
    <tr><td><?php echo htmlspecialchars($a['title']); ?></td><td><?php echo htmlspecialchars($a['category']); ?></td><td><a href="?page=article&id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline">Edit</a></td></tr>
    <?php endforeach; ?>
</tbody></table></div>

<?php elseif ($page === 'article'): ?>
<?php $kb = loadJSON('kb.json'); $editA = null; foreach ($kb['articles'] as $a) { if ($a['id'] === ($_GET['id'] ?? '')) { $editA = $a; } } ?>
<div class="page-header"><div><h1 class="page-title"><?php echo $editA ? 'Edit Article' : 'New Article'; ?></h1></div><a href="?page=kb" class="btn btn-outline">&larr; Back</a></div>
<div class="card" style="max-width:800px;">
    <form method="POST" action="?action=save_article">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editA['id'] ?? ''); ?>">
        <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($editA['title'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">Category</label><select name="category" class="form-select"><?php foreach ($kb['categories'] as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo ($editA['category'] ?? '')===$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label class="form-label">Content (HTML allowed)</label><textarea name="content" class="form-textarea" rows="12"><?php echo htmlspecialchars($editA['content'] ?? ''); ?></textarea></div>
        <button type="submit" class="btn btn-primary">Save Article</button>
    </form>
</div>

<?php elseif ($page === 'pages'): ?>
<?php $cpages = loadJSON('pages.json'); ?>
<div class="page-header"><div><h1 class="page-title">Custom Pages</h1></div><a href="?page=edit-page" class="btn btn-primary">New Page</a></div>
<div class="table-container"><table class="table"><thead><tr><th>Title</th><th>Slug</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($cpages as $p): ?>
    <tr><td><?php echo htmlspecialchars($p['title']); ?></td><td>/?page=<?php echo htmlspecialchars($p['slug']); ?></td><td><span class="badge badge-<?php echo !empty($p['enabled'])?'success':'secondary'; ?>"><?php echo !empty($p['enabled'])?'Live':'Hidden'; ?></span></td><td><a href="?page=edit-page&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline">Edit</a></td></tr>
    <?php endforeach; ?>
</tbody></table></div>

<?php elseif ($page === 'edit-page'): ?>
<?php $cpages = loadJSON('pages.json'); $editP = null; foreach ($cpages as $p) { if ($p['id'] === ($_GET['id'] ?? '')) { $editP = $p; } } ?>
<div class="page-header"><div><h1 class="page-title"><?php echo $editP ? 'Edit Page' : 'New Page'; ?></h1></div><a href="?page=pages" class="btn btn-outline">&larr; Back</a></div>
<div class="card" style="max-width:800px;">
    <form method="POST" action="?action=save_page">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editP['id'] ?? ''); ?>">
        <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($editP['title'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">Slug</label><input type="text" name="slug" class="form-input" value="<?php echo htmlspecialchars($editP['slug'] ?? ''); ?>" required></div>
        <div class="form-group"><label class="form-label">SEO title</label><input type="text" name="seo_title" class="form-input" value="<?php echo htmlspecialchars($editP['seo_title'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">SEO description</label><input type="text" name="seo_description" class="form-input" value="<?php echo htmlspecialchars($editP['seo_description'] ?? ''); ?>"></div>
        <div class="form-group"><label class="form-label">Content (HTML allowed)</label><textarea name="content" class="form-textarea" rows="12"><?php echo htmlspecialchars($editP['content'] ?? ''); ?></textarea></div>
        <div class="form-group"><label><input type="checkbox" name="enabled" <?php echo (!isset($editP['enabled']) || $editP['enabled'])?'checked':''; ?>> Enabled</label></div>
        <button type="submit" class="btn btn-primary">Save Page</button>
    </form>
</div>

<?php else: ?>
<div class="alert alert-warning">Page not found or you don't have permission.</div>
<?php endif; ?>
