<?php 

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', function () {

    if (!is_product()) return;

    global $post;

    $target_ids = [16988]; // add more if needed

    if (!in_array(get_the_ID(), $target_ids)) return;

    ?>
    
    <!-- Modal HTML -->
    <div id="welcomeModal" class="modal hidden">
      <div class="modal-overlay"></div>

      <div class="modal-box">
        <button id="modalClose" class="modal-close">×</button>

        <div class="warning-box">
          <span class="warning-icon">⚠️</span>
          <p>
            এই মডিউল দিয়ে রাউটার বা ONU চালানো যাবে না। এটি শুধুমাত্র ছোট লোডের জন্য উপযুক্ত।
          </p>
        </div>
        <div style="margin-top:15px; overflow-x:auto;">
  <table style="width:100%; border-collapse:collapse; font-size:14px; text-align:center;">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="border:1px solid #ddd; padding:8px;">আউটপুট ভোল্টেজ</th>
        <th style="border:1px solid #ddd; padding:8px;">ম্যাক্স কারেন্ট</th>
        <th style="border:1px solid #ddd; padding:8px;">গড় পাওয়ার</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="border:1px solid #ddd; padding:8px;">5V</td>
        <td style="border:1px solid #ddd; padding:8px;">1.0A</td>
        <td style="border:1px solid #ddd; padding:8px;">5W</td>
      </tr>
      <tr>
        <td style="border:1px solid #ddd; padding:8px;">9V</td>
        <td style="border:1px solid #ddd; padding:8px;">0.7A</td>
        <td style="border:1px solid #ddd; padding:8px;">6.3W</td>
      </tr>
      <tr>
        <td style="border:1px solid #ddd; padding:8px;">12V</td>
        <td style="border:1px solid #ddd; padding:8px;">0.5A</td>
        <td style="border:1px solid #ddd; padding:8px;">6W</td>
      </tr>
      <tr>
        <td style="border:1px solid #ddd; padding:8px;">15V</td>
        <td style="border:1px solid #ddd; padding:8px;">0.4A</td>
        <td style="border:1px solid #ddd; padding:8px;">6W</td>
      </tr>
      <tr>
        <td style="border:1px solid #ddd; padding:8px;">19V</td>
        <td style="border:1px solid #ddd; padding:8px;">0.3A</td>
        <td style="border:1px solid #ddd; padding:8px;">5.7W</td>
      </tr>
      <tr>
        <td style="border:1px solid #ddd; padding:8px;">24V</td>
        <td style="border:1px solid #ddd; padding:8px;">0.25A</td>
        <td style="border:1px solid #ddd; padding:8px;">6W</td>
      </tr>
    </tbody>
  </table>
</div><div style="margin-top:10px; font-size:13px; color:#b91c1c;">
⚠️ হাই ভোল্টেজে কারেন্ট কমে যাবে। এই মডিউল হাই পাওয়ার ডিভাইস (যেমন রাউটার/ONU) চালানোর জন্য উপযুক্ত নয়।
</div>
      </div>
    </div>

    <style>
    .modal { position:fixed; inset:0; z-index:9999; display:none; align-items:center; justify-content:center; }
    .modal.show { display:flex; }
    .modal-overlay { position:absolute; inset:0; background:rgba(0,0,0,.5); }
    .modal-box { position:relative; background:#fff; padding:20px; border-radius:12px; width:90%; max-width:380px; z-index:10; }
    .modal-close { position:absolute; top:8px; right:12px; font-size:20px; border:none; background:none; cursor:pointer; }
    .warning-box { display:flex; gap:8px; padding:12px; border:1px solid #fca5a5; background:#fef2f2; color:#b91c1c; border-radius:8px; }
    </style>

    <script>
    (function () {
      const KEY = "smdp_modal_time_lcbst"; // unique per product
      const DURATION = 24 * 60 * 60 * 1000;

      const modal = document.getElementById("welcomeModal");
      const closeBtn = document.getElementById("modalClose");

      if (!modal) return;

      function shouldShow() {
        const last = localStorage.getItem(KEY);
        if (!last) return true;
        return Date.now() - parseInt(last, 10) > DURATION;
      }

      function open() {
        modal.classList.add("show");
        document.body.style.overflow = "hidden";
      }

      function close() {
        modal.classList.remove("show");
        localStorage.setItem(KEY, Date.now());
        document.body.style.overflow = "";
      }

      closeBtn.addEventListener("click", close);

      modal.addEventListener("click", function (e) {
        if (e.target === modal) close();
      });

      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") close();
      });

      document.addEventListener("DOMContentLoaded", function () {
        if (shouldShow()) {
          setTimeout(open, 500);
        }
      });
    })();
    </script>

    <?php
});