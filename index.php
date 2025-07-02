<div style="display: flex; align-items: center; gap: 24px; background: linear-gradient(90deg, #004aad 80%, #222 100%); color: #fff; padding: 32px 40px 24px 40px; border-top-left-radius: 22px; border-top-right-radius: 22px; box-shadow: 0 2px 12px rgba(0,74,173,0.08); margin-bottom: 20px;">
  <img src="images/logo.png" alt="WeConnect Logo" style="width: 90px; height: 90px; border-radius: 50%; background: #fff; object-fit: contain; box-shadow: 0 2px 12px rgba(0,74,173,0.15);" />
  <div>
    <h1 style="font-size: 2.7em; font-weight: 800; margin: 0; letter-spacing: 1px; text-shadow: 0 2px 8px #00337a44;">WeConnect</h1>
    <div style="font-style: italic; font-size: 1.15em; margin-top: 6px; color: #c7e0ff; font-weight: 500;">The future is bright with us!</div>
  </div>
</div>

<div style="display: flex; flex-wrap: wrap; width: 100%; margin-bottom: 30px; border-radius: 22px; overflow: hidden; box-shadow: 0 0 12px rgba(0,0,0,0.1);">
  <div style="flex: 1 1 340px; min-width: 300px; background: rgba(0,0,0,0.7); padding: 30px; border-right: 2px solid #004aad;">
    <div style="margin-bottom: 20px;">
      <h2 style="color: #4eaaff; border-bottom: 2px solid #004aad; padding-bottom: 6px;">Our Vision</h2>
      <p style="line-height: 1.7; font-size: 1.05em; color: #e0e0e0; font-weight: 400;">
        <i>To become the leading digital education platform in Africa by promoting inclusive, quality education and leveraging technology to bridge communication gaps between learners, parents, and educators.</i>
      </p>
    </div>
    <div style="margin-bottom: 20px;">
      <h2 style="color: #4eaaff; border-bottom: 2px solid #004aad; padding-bottom: 6px;">Our Mission</h2>
      <p style="line-height: 1.7; font-size: 1.05em; color: #e0e0e0; font-weight: 400;">
        <i>To connect learners, teachers, and parents in a collaborative online environment that empowers every learner to reach their full academic potential.</i>
      </p>
    </div>
    <div style="margin-bottom: 20px;">
      <h2 style="color: #4eaaff; border-bottom: 2px solid #004aad; padding-bottom: 6px;">Contact Us</h2>
      <p style="color: #c7e0ff;">
        📞 Phone: +27 123 456 7890<br />
        📧 Email: <a href="mailto:support@weconnect.com" style="color: #4eaaff; text-decoration: underline;">support@weconnect.com</a><br />
        🌍 Website: <a href="https://www.weconnect.com" target="_blank" style="color: #4eaaff; text-decoration: underline;">www.weconnect.com</a>
      </p>
    </div>
    <div>
      <h2 style="color: #4eaaff; border-bottom: 2px solid #004aad; padding-bottom: 6px;">Follow Us</h2>
      <div style="display: flex; gap: 16px; margin-top: 10px;">
        <a href="https://facebook.com/weconnect" target="_blank"><img src="images/facebook.png" style="width: 32px; height: 32px; border-radius: 8px; background: #fff; padding: 4px;"></a>
        <a href="https://wa.me/271234567890" target="_blank"><img src="images/whatsapp.png" style="width: 32px; height: 32px; border-radius: 8px; background: #fff; padding: 4px;"></a>
        <a href="https://instagram.com/weconnect" target="_blank"><img src="images/instagram.png" style="width: 32px; height: 32px; border-radius: 8px; background: #fff; padding: 4px;"></a>
        <a href="https://linkedin.com/company/weconnect" target="_blank"><img src="images/linkedin.png" style="width: 32px; height: 32px; border-radius: 8px; background: #fff; padding: 4px;"></a>
      </div>
    </div>
  </div>

  <div style="flex: 2 1 600px; min-width: 320px; padding: 38px 32px; background:rgba(0,0,0,0.1); display: flex; flex-direction: column; align-items: center; justify-content: center;">
    <div style="position: relative; width: 100%; max-width: 480px; height: 320px; overflow: hidden; border-radius: 18px; box-shadow: 0 0 18px rgba(0,0,0,0.25); background: #222; margin-bottom: 24px;">
      <div class="mySlides" style="display:none; height:100%;"><img src="images/img12.jpg" alt="Slide 1" style="width:100%; height:100%; object-fit:cover; border-radius:18px;"></div>
      <div class="mySlides" style="display:none; height:100%;"><img src="images/img14.jpg" alt="Slide 2" style="width:100%; height:100%; object-fit:cover; border-radius:18px;"></div>
      <div class="mySlides" style="display:none; height:100%;"><img src="images/img16.jpg" alt="Slide 3" style="width:100%; height:100%; object-fit:cover; border-radius:18px;"></div>
    </div>
    <a href="login.html" style="background: linear-gradient(90deg, #004aad 70%, #007fff 100%); color: #fff; padding: 16px 48px; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 1.25em; letter-spacing: 1px; box-shadow: 0 5px 16px rgba(0, 74, 173, 0.25); transition: background 0.3s;">Go to Login</a>
  </div>
</div>

<script>
  let slideIndex = 0;
  showSlides();
  function showSlides() {
    const slides = document.getElementsByClassName("mySlides");
    for (let i = 0; i < slides.length; i++) {
      slides[i].style.display = "none";
    }
    slideIndex++;
    if (slideIndex > slides.length) { slideIndex = 1; }
    slides[slideIndex - 1].style.display = "block";
    setTimeout(showSlides, 4000);
  }
</script>
