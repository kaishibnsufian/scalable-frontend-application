<?php
  $API_BASE = getenv("API_BASE_URL");
  if (!$API_BASE) $API_BASE = "https://backendvid-c9b6agdya4g8egbt.francecentral-01.azurewebsites.net";
  $videoId = isset($_GET["id"]) ? $_GET["id"] : "";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Watch Video</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body { background:#0b1220; }
    .navbar-blur { background:rgba(16,24,40,.75); backdrop-filter: blur(10px); }
    .card-dark { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); }
    .text-soft { color:rgba(255,255,255,.75); }
    .comment-box { white-space: pre-wrap; }
  </style>

  <script>
    const API_BASE = <?= json_encode($API_BASE) ?>;
    const VIDEO_ID = <?= json_encode($videoId) ?>;

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

    async function loadVideo() {
      if (!VIDEO_ID) {
        document.getElementById("main").innerHTML = `<div class="text-soft">Missing video id.</div>`;
        return;
      }

      try {
        const r = await fetch(`${API_BASE}/api/videos/${encodeURIComponent(VIDEO_ID)}`);
        const v = await r.json();
        if (!r.ok) throw new Error(v.error || "Failed to load video.");

        document.getElementById("title").textContent = v.title || "Untitled";
        document.getElementById("desc").textContent = v.description || "";

        const player = document.getElementById("player");
        player.src = v.blobUrl;

        renderComments(v.comments || []);
      } catch (e) {
        document.getElementById("main").innerHTML = `<div class="text-soft">${escapeHtml(e.message)}</div>`;
      }
    }

    function renderComments(comments) {
      const list = document.getElementById("commentList");
      const userId = getOrCreateUserId();

      if (!comments.length) {
        list.innerHTML = `<div class="text-soft">No comments yet.</div>`;
        return;
      }

      list.innerHTML = comments.map(c => {
        const mine = c.userId === userId;
        return `
          <div class="card card-dark p-3 mb-2">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <div class="text-white fw-semibold">${escapeHtml(c.authorName || "Anonymous")}</div>
                <div class="text-soft small">${escapeHtml(c.createdAt || "")}</div>
              </div>
              ${mine ? `
                <button class="btn btn-sm btn-outline-danger" onclick="deleteComment('${c.id}')">
                  <i class="bi bi-trash"></i>
                </button>
              ` : ``}
            </div>
            <div class="comment-box text-white mt-2">${escapeHtml(c.text || "")}</div>
          </div>
        `;
      }).join("");
    }

    async function addComment(ev) {
      ev.preventDefault();

      const userId = getOrCreateUserId();
      const authorName = document.getElementById("authorName").value.trim() || "Anonymous";
      const text = document.getElementById("commentText").value.trim();

      if (!text) { alert("Please write a comment."); return; }

      const btn = document.getElementById("commentBtn");
      btn.disabled = true;
      btn.innerHTML = "Posting...";

      try {
        const r = await fetch(`${API_BASE}/api/videos/${encodeURIComponent(VIDEO_ID)}/comments`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ userId, authorName, text })
        });

        const data = await r.json();
        if (!r.ok) throw new Error(data.error || "Failed to add comment.");

        document.getElementById("commentText").value = "";
        await loadVideo();
      } catch (e) {
        alert(e.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = "Post";
      }
    }

    async function deleteComment(commentId) {
      const userId = getOrCreateUserId();
      if (!confirm("Delete your comment?")) return;

      try {
        const r = await fetch(`${API_BASE}/api/videos/${encodeURIComponent(VIDEO_ID)}/comments/${encodeURIComponent(commentId)}`, {
          method: "DELETE",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ userId })
        });

        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.error || "Failed to delete comment.");

        await loadVideo();
      } catch (e) {
        alert(e.message);
      }
    }

    document.addEventListener("DOMContentLoaded", () => {
      getOrCreateUserId();
      loadVideo();
    });
  </script>
</head>

<body>
  <nav class="navbar navbar-dark navbar-blur border-bottom border-light border-opacity-10 sticky-top">
    <div class="container py-2">
      <a class="navbar-brand fw-semibold" href="index.php">
        <i class="bi bi-arrow-left me-2"></i>Back
      </a>
      <span class="text-soft small">Watch & Comment</span>
    </div>
  </nav>

  <main class="container py-4" id="main">
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="card card-dark p-3">
          <h3 id="title" class="text-white mb-1">Loading...</h3>
          <div id="desc" class="text-soft mb-3"></div>

          <video id="player" class="w-100 rounded" controls playsinline></video>

          <div class="text-soft small mt-2">
            If the video does not play, your Blob container may be private. For a demo, set container access level to Blob/Public or switch to SAS URLs.
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card card-dark p-3 mb-3">
          <h5 class="text-white mb-3">Add a comment</h5>
          <form onsubmit="addComment(event)">
            <div class="mb-2">
              <label class="form-label text-soft">Name</label>
              <input id="authorName" class="form-control" placeholder="Optional" />
            </div>
            <div class="mb-2">
              <label class="form-label text-soft">Comment</label>
              <textarea id="commentText" class="form-control" rows="4" placeholder="Write something..."></textarea>
            </div>
            <button id="commentBtn" class="btn btn-primary w-100" type="submit">
              Post <i class="bi bi-send ms-1"></i>
            </button>
          </form>
        </div>

        <div class="card card-dark p-3">
          <h5 class="text-white mb-2">Comments</h5>
          <div id="commentList"></div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

