<?php
// superadmin/karyawan.php - Employee & User Management
require_once '../includes/auth_check.php';
check_auth(['superadmin']);

require_once '../layout/header.php';

$msg = '';
$err = '';

// Create User
if (isset($_POST['tambah_user'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $password = $_POST['password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    if (!empty($name) && !empty($phone) && !empty($password)) {
        // Cek duplicate
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE phone='$phone' OR (email != '' AND email='$email')");
        if (mysqli_num_rows($cek) > 0) {
            $err = 'Nomor HP atau Email sudah dipakai oleh akun lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . rand(10, 99);
            
            $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, username, password, phone, role, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssssss", $name, $email, $username, $hash, $phone, $role, $address);
            if (mysqli_stmt_execute($stmt)) {
                $msg = 'Akun karyawan baru berhasil dibuat!';
            } else {
                $err = 'Gagal menyimpan: ' . mysqli_error($conn);
            }
        }
    } else {
        $err = 'Nama, nomor HP, dan password wajib diisi.';
    }
}

// Update User
if (isset($_POST['edit_user'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $password = $_POST['password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    if (!empty($name) && !empty($phone)) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, phone=?, role=?, address=?, password=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssssssi", $name, $email, $phone, $role, $address, $hash, $id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, phone=?, role=?, address=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssssi", $name, $email, $phone, $role, $address, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Data karyawan berhasil diperbarui!';
        } else {
            $err = 'Gagal memperbarui data: ' . mysqli_error($conn);
        }
    }
}

// Delete User
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id == $_SESSION['user_id']) {
        $err = 'Anda tidak dapat menghapus akun Anda sendiri!';
    } else {
        if (mysqli_query($conn, "DELETE FROM users WHERE id='$id'")) {
            $msg = 'Akun berhasil dihapus!';
        } else {
            $err = 'Gagal menghapus akun: ' . mysqli_error($conn);
        }
    }
}

// Role filter
$filter_role = $_GET['role'] ?? '';
$where = "";
if (!empty($filter_role)) {
    $r_safe = mysqli_real_escape_string($conn, $filter_role);
    $where = "WHERE role='$r_safe'";
}
$users_list = mysqli_query($conn, "SELECT * FROM users $where ORDER BY role ASC, id DESC");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Manajemen Karyawan & Pengguna</h1>
        <p class="text-slate-400 text-sm">Kelola akun Admin/Kasir, Superadmin, dan Pelanggan sistem.</p>
    </div>
    <div class="flex gap-2">
        <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" 
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-100 transition flex items-center gap-2">
            <i class="fas fa-user-plus"></i> Tambah Karyawan Baru
        </button>
    </div>
</div>

<?php if ($msg): ?>
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-500"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-red-500"></i>
        <span><?= htmlspecialchars($err); ?></span>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="flex gap-2 mb-6 border-b border-slate-200 pb-3 overflow-x-auto no-scrollbar">
    <a href="karyawan.php" class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 <?= empty($filter_role) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-white text-slate-600 hover:bg-slate-100'; ?>">
        Semua Karyawan
    </a>
    <a href="karyawan.php?role=admin" class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 <?= $filter_role === 'admin' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-white text-slate-600 hover:bg-slate-100'; ?>">
        <i class="fas fa-cash-register mr-1"></i> Admin / Kasir
    </a>
    <a href="karyawan.php?role=superadmin" class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 <?= $filter_role === 'superadmin' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-white text-slate-600 hover:bg-slate-100'; ?>">
        <i class="fas fa-crown mr-1"></i> Superadmin (Owner)
    </a>
</div>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-6 py-4">Nama Lengkap</th>
                    <th class="px-6 py-4">Kontak (Email & No. WA)</th>
                    <th class="px-6 py-4">Role Akses</th>
                    <th class="px-6 py-4">Alamat</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while ($u = mysqli_fetch_assoc($users_list)): ?>
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 font-bold flex items-center justify-center uppercase text-xs">
                                <?= substr($u['name'] ?: 'U', 0, 1); ?>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800"><?= htmlspecialchars($u['name']); ?></p>
                                <p class="text-xs text-slate-400">@<?= htmlspecialchars($u['username'] ?? '-'); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-medium text-slate-700"><?= htmlspecialchars($u['phone']); ?></p>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($u['email'] ?? '-'); ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($u['role'] === 'superadmin'): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                <i class="fas fa-crown mr-1"></i> Owner
                            </span>
                        <?php elseif ($u['role'] === 'admin'): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">
                                <i class="fas fa-cash-register mr-1"></i> Admin / Kasir
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-slate-100 text-slate-600">
                                Pelanggan
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500 max-w-xs truncate">
                        <?= htmlspecialchars($u['address'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='editUser(<?= json_encode($u); ?>)' class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 transition flex items-center justify-center text-xs">
                                <i class="fas fa-pen"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="?hapus=<?= $u['id']; ?>" onclick="return confirm('Hapus akun <?= htmlspecialchars(addslashes($u['name'])); ?>?')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 transition flex items-center justify-center text-xs">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-slate-800">Tambah Akun Karyawan / Pengguna</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Lengkap *</label>
                <input type="text" name="name" required placeholder="Contoh: Rina Anggraini" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">No. WhatsApp / HP *</label>
                    <input type="tel" name="phone" required placeholder="08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Email</label>
                    <input type="email" name="email" placeholder="karyawan@laundry.com" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Role Akses *</label>
                    <select name="role" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="admin">Admin (Pekerja / Kasir)</option>
                        <option value="superadmin">Superadmin (Pemilik / Owner)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Password *</label>
                    <input type="password" name="password" required placeholder="Minimal 6 karakter" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Alamat / Cabang Penugasan</label>
                <textarea name="address" rows="2" placeholder="Outlet Cabang Sudirman..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 text-sm">Batal</button>
                <button type="submit" name="tambah_user" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-100">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-slate-800">Edit Akun Pengguna</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="id" id="edit_id">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Lengkap *</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">No. WhatsApp / HP *</label>
                    <input type="tel" name="phone" id="edit_phone" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Role Akses *</label>
                    <select name="role" id="edit_role" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="admin">Admin (Pekerja / Kasir)</option>
                        <option value="superadmin">Superadmin (Pemilik / Owner)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Password Baru (Kosongkan jika tetap)</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak diganti" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Alamat</label>
                <textarea name="address" id="edit_address" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 text-sm">Batal</button>
                <button type="submit" name="edit_user" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-100">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('edit_id').value = u.id;
    document.getElementById('edit_name').value = u.name;
    document.getElementById('edit_phone').value = u.phone;
    document.getElementById('edit_email').value = u.email || '';
    document.getElementById('edit_role').value = u.role;
    document.getElementById('edit_address').value = u.address || '';
    document.getElementById('modalEdit').classList.remove('hidden');
}
</script>

<?php require_once '../layout/footer.php'; ?>
