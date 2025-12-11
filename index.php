<?php
  // Set this in Azure Frontend App Service > Configuration:
  // API_BASE_URL = https://YOUR-BACKEND-APP.azurewebsites.net
  $API_BASE = getenv("API_BASE_URL");
  if (!$API_BASE) $API_BASE = "https://backendvid-c9b6agdya4g8egbt.francecentral-01.azurewebsites.net";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Scalable Video Share</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body { background:#0b1220; }
    .navbar-blur { background:rgba(16,24,40,.75); backdrop-filter: blur(10px); }
    .card-dark { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); }
    .text-soft { color:rgba(255,255,255,.75); }
    .video-thumb{
      width:100%; height:170px; border-radius:12px;
      background: linear-gradient(135deg, rgba(13,110,253,.25), rgba(111,66,193,.25));
      display:flex; align-items:center; justify-content:center;
      border:1px solid rgba(255,255,255,.12);
    }
    .fab-upload{
      position:fixed; right:22px; bottom:22px;
      width:56px; height:56px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      box-shadow:0 18px 40px rgba(0,0,0,.45);
    }
  </style>

  <script>
    const API_BASE = <?= json_encode($API_BASE) ?>;

    function getOrCreateUserId() {
      let id = localStorage.getItem("svs_userId");
      if (!id) {
        id = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()));
        localStorage.setItem("svs_userId", id);
      }
      return id;
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    async function loadVideos() {
      const grid = document.getElementById("videoGrid");
      grid.innerHTML = `<div class="text-soft">Loading videos...</div>`;

      try {
        const r = await fetch(`${API_BASE}/api/videos`);
        const data = await r.json();

        if (!data.items || data.items.length === 0) {
          grid.innerHTML = `<div class="text-soft">No videos yet. Click + to upload.</div>`;
          return;
        }

        grid.innerHTML = data.items.map(v => `
          <div class="col-sm-6 col-lg-4">
            <div class="card card-dark p-3 h-100">
              <div class="video-thumb mb-3">
                <div class="text-center">
                  <i class="bi bi-play-circle" style="font-size:42px;color:white;"></i>
                  <div class="text-soft small mt-1">Click Watch</div>
                </div>
              </div>
              <h5 class="text-white mb-1">${escapeHtml(v.title || "Untitled")}</h5>
              <div class="text-soft small mb-3">${escapeHtml(v.description || "")}</div>
              <div class="d-flex gap-2 mt-auto">
                <a class="btn btn-primary w-100" href="watch.php?id=${encodeURIComponent(v.id)}">
                  Watch <i class="bi bi-arrow-right-short"></i>
                </a>
                <button class="btn btn-outline-light" onclick="copyLink('${v.id}')">
                  <i class="bi bi-link-45deg"></i>
                </button>
              </div>
            </div>
          </div>
        `).join("");
      } catch (e) {
        grid.innerHTML = `<div class="text-soft">Failed to load. Check API_BASE_URL and CORS.</div>`;
      }
    }

    function copyLink(id) {
      const base = location.origin + location.pathname.replace(/index\.php$/,"");
      const url = `${base}watch.php?id=${encodeURIComponent(id)}`;
      navigator.clipboard.writeText(url);
      alert("Watch link copied.");
    }

    async function uploadVideo(ev) {
      ev.preventDefault();

      const title = document.getElementById("title").value.trim();
      const description = document.getElementById("description").value.trim();
      const file = document.getElementById("video").files[0];

      if (!title || !file) {
        alert("Title and video file are required.");
        return;
      }

      const btn = document.getElementById("uploadBtn");
      btn.disabled = true;
      btn.innerHTML = "Uploading...";

      try {
        const fd = new FormData();
        fd.append("title", title);
        fd.append("description", description);
        fd.append("video", file);

        const r = await fetch(`${API_BASE}/api/videos`, { method: "POST", body: fd });
        const data = await r.json();
        if (!r.ok) throw new Error(data.error || "Upload failed.");

        bootstrap.Modal.getInstance(document.getElementById("uploadModal")).hide();
        document.getElementById("uploadForm").reset();
        await loadVideos();
      } catch (e) {
        alert(e.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = "Upload";
      }
    }

    document.addEventListener("DOMContentLoaded", () => {
      getOrCreateUserId();
      loadVideos();
    });
  </script>
</head>

<body>
  <nav class="navbar navbar-dark navbar-blur border-bottom border-light border-opacity-10 sticky-top">
    <div class="container py-2">
      <a class="navbar-brand fw-semibold" href="index.php">
        <i class="bi bi-camera-reels me-2"></i>Scalable Video Share
      </a>
      <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-upload me-1"></i> Upload
      </button>
    </div>
  </nav>

  <main class="container py-4">
    <div class="mb-3">
      <h2 class="text-white mb-1">All Videos</h2>
      <div class="text-soft">Upload videos, watch, and comment.</div>
    </div>

    <div id="videoGrid" class="row g-3"></div>
  </main>

  <button class="btn btn-primary fab-upload" data-bs-toggle="modal" data-bs-target="#uploadModal" aria-label="Upload">
    <i class="bi bi-plus-lg"></i>
  </button>

  <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark text-white border border-light border-opacity-10">
        <div class="modal-header">
          <h5 class="modal-title">Upload a video</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form id="uploadForm" onsubmit="uploadVideo(event)">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input id="title" class="form-control" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Description (optional)</label>
              <input id="description" class="form-control" />
            </div>
            <div>
              <label class="form-label">Video file</label>
              <input id="video" type="file" class="form-control" accept="video/*" required />
              <div class="form-text text-soft">If upload fails, reduce file size or increase backend upload limit.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancel</button>
            <button id="uploadBtn" class="btn btn-primary" type="submit">Upload</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

