
    <!-- CARD: Pengaturan Upload Jobsheet -->
    <div class="card" style="margin-bottom:24px">
        <div class="section-title">⚙️ Pengaturan Upload Jobsheet</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Topik</th>
                        <th>Judul Jobsheet</th>
                        <th style="text-align:center">Perlu Upload?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $jobsheets = $pdo->query("SELECT id, topik, judul, perlu_upload FROM content WHERE tipe = 'jobsheet' ORDER BY topik")->fetchAll();
                    foreach ($jobsheets as $js):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $js['topik']))) ?></td>
                        <td><?= htmlspecialchars($js['judul']) ?></td>
                        <td style="text-align:center">
                            <form method="POST" action="api/toggle_upload_jobsheet.php" style="display:inline">
                                <input type="hidden" name="token" value="smkbansari2024">
                                <input type="hidden" name="content_id" value="<?= $js['id'] ?>">
                                <input type="hidden" name="nilai" value="<?= $js['perlu_upload'] ? 0 : 1 ?>">
                                <button type="submit" class="btn <?= $js['perlu_upload'] ? 'btn-success' : 'btn-outline' ?>" style="padding:4px 16px;font-size:13px">
                                    <?= $js['perlu_upload'] ? '✅ Aktif' : '⬜ Nonaktif' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CARD: Submission Jobsheet Siswa -->
    <div class="card" style="margin-bottom:24px">
        <div class="section-title">📎 Submission Jobsheet Siswa</div>
        <div class="table-wrap">
            <?php
            $submissions = $pdo->query("
                SELECT js.id, u.nama, u.nis, u.kelas, c.judul, c.topik,
                       js.file_path, js.file_original_name, js.nilai, js.created_at
                FROM jobsheet_submissions js
                JOIN users u ON u.id = js.user_id
                JOIN content c ON c.id = js.content_id
                ORDER BY js.created_at DESC
            ")->fetchAll();
            ?>
            <?php if ($submissions): ?>
            <table>
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas</th>
                        <th>Jobsheet</th>
                        <th>File</th>
                        <th>Tgl Upload</th>
                        <th style="text-align:center">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td><?= htmlspecialchars($sub['nama']) ?><br><small style="color:#888"><?= $sub['nis'] ?></small></td>
                        <td><?= htmlspecialchars($sub['kelas'] ?? '-') ?></td>
                        <td>
                            <small><?= htmlspecialchars(ucwords(str_replace('_',' ',$sub['topik']))) ?></small><br>
                            <?= htmlspecialchars(mb_strimwidth($sub['judul'], 0, 40, '...')) ?>
                        </td>
                        <td>
                            <a href="/<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" style="color:#0f3460;font-size:13px">
                                📄 <?= htmlspecialchars(mb_strimwidth($sub['file_original_name'], 0, 25, '...')) ?>
                            </a>
                        </td>
                        <td style="font-size:12px;color:#666"><?= date('d/m/Y H:i', strtotime($sub['created_at'])) ?></td>
                        <td style="text-align:center">
                            <form method="POST" action="api/nilai_jobsheet.php" style="display:flex;gap:6px;justify-content:center;align-items:center">
                                <input type="hidden" name="token" value="smkbansari2024">
                                <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                <input type="number" name="nilai" min="0" max="100" step="0.5"
                                    value="<?= $sub['nilai'] ?? '' ?>"
                                    style="width:60px;padding:4px;border:1px solid #ccc;border-radius:4px;font-size:13px">
                                <button type="submit" class="btn btn-primary" style="padding:4px 10px;font-size:12px">Simpan</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">Belum ada submission jobsheet</div>
            <?php endif; ?>
        </div>
    </div>

