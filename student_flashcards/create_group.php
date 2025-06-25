<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Group - FlashGenius</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #7F56D9;
      --primary-light: #9E77ED;
      --primary-dark: #6941C6;
      --secondary: #36BFFA;
      --success: #32D583;
      --warning: #FDB022;
      --danger: #F97066;
      --text-dark: #101828;
      --text-medium: #475467;
      --text-light: #98A2B3;
      --bg-light: #F9FAFB;
      --bg-white: #FFFFFF;
      --border-light: #EAECF0;
      --shadow-xs: 0 1px 2px rgba(16, 24, 40, 0.05);
      --shadow-sm: 0 1px 3px rgba(16, 24, 40, 0.1), 0 1px 2px rgba(16, 24, 40, 0.06);
      --shadow-md: 0 4px 6px -1px rgba(16, 24, 40, 0.1), 0 2px 4px -1px rgba(16, 24, 40, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(16, 24, 40, 0.1), 0 4px 6px -2px rgba(16, 24, 40, 0.05);
      --shadow-xl: 0 20px 25px -5px rgba(16, 24, 40, 0.1), 0 10px 10px -5px rgba(16, 24, 40, 0.04);
      --radius-sm: 6px;
      --radius-md: 8px;
      --radius-lg: 12px;
      --radius-xl: 16px;
      --radius-full: 9999px;
      --space-xs: 4px;
      --space-sm: 8px;
      --space-md: 16px;
      --space-lg: 24px;
      --space-xl: 32px;
      --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', sans-serif;
    }

    body {
      background-color: var(--bg-light);
      color: var(--text-dark);
      line-height: 1.5;
    }

    /* App Container - Mobile First */
    .app-container {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar - Desktop */
    .sidebar {
      display: none;
      width: 280px;
      min-width: 280px;
      height: 100vh;
      background-color: var(--bg-white);
      border-right: 1px solid var(--border-light);
      padding: var(--space-xl);
      position: sticky;
      top: 0;
      overflow-y: auto;
      z-index: 10;
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      margin-bottom: var(--space-xl);
      padding-bottom: var(--space-md);
      border-bottom: 1px solid var(--border-light);
    }

    .logo {
      width: 32px;
      height: 32px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
    }

    .app-name {
      font-weight: 600;
      font-size: 18px;
      color: var(--text-dark);
    }

    .nav-section {
      margin-bottom: var(--space-xl);
    }

    .section-title {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-light);
      margin-bottom: var(--space-sm);
      font-weight: 600;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-xs);
      text-decoration: none;
      color: var(--text-medium);
      font-weight: 500;
      transition: var(--transition);
    }

    .nav-item:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }

    .nav-item.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
      font-weight: 600;
    }

    .nav-item i {
      width: 20px;
      text-align: center;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: var(--space-md);
      position: relative;
      background-color: var(--bg-light);
      width: 100%;
    }

    /* Header - Mobile */
    .header {
      background-color: var(--bg-white);
      padding: var(--space-md);
      position: sticky;
      top: 0;
      z-index: 10;
      box-shadow: var(--shadow-sm);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-lg);
    }

    .header-title {
      font-weight: 600;
      font-size: 18px;
    }

    .header-actions {
      display: flex;
      gap: var(--space-sm);
    }

    .header-btn {
      width: 36px;
      height: 36px;
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-light);
      border: none;
      color: var(--text-medium);
      cursor: pointer;
    }

    /* Form Styles */
    .form-container {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      box-shadow: var(--shadow-sm);
      max-width: 600px;
      margin: 0 auto;
    }

    .form-title {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: var(--space-lg);
      color: var(--primary-dark);
      text-align: center;
    }

    .form-group {
      margin-bottom: var(--space-lg);
    }

    .form-label {
      display: block;
      font-weight: 500;
      margin-bottom: var(--space-sm);
      color: var(--text-dark);
    }

    .form-control {
      width: 100%;
      padding: var(--space-sm) var(--space-md);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-size: 14px;
      transition: var(--transition);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.1);
    }

    .form-textarea {
      min-height: 120px;
      resize: vertical;
    }

    .image-upload {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: var(--space-md);
      padding: var(--space-xl) 0;
      border: 2px dashed var(--border-light);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-lg);
      cursor: pointer;
      transition: var(--transition);
    }

    .image-upload:hover {
      border-color: var(--primary-light);
      background-color: #F9F5FF;
    }

    .image-upload i {
      font-size: 32px;
      color: var(--primary);
    }

    .image-upload-text {
      font-size: 14px;
      color: var(--text-medium);
    }

    .image-upload-text span {
      color: var(--primary);
      font-weight: 500;
    }

    .btn-submit {
      width: 100%;
      padding: var(--space-md);
      border-radius: var(--radius-md);
      border: none;
      background-color: var(--primary);
      color: white;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      font-size: 16px;
    }

    .btn-submit:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
    }

    /* Bottom Navigation - Mobile */
    .bottom-nav-container {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 20;
    }

    .bottom-nav {
      background-color: var(--bg-white);
      display: flex;
      justify-content: space-around;
      align-items: center;
      padding: var(--space-sm) 0;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
      position: relative;
      height: 60px;
    }

    .nav-item-mobile {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: var(--text-light);
      font-size: 10px;
      gap: 4px;
      z-index: 1;
      width: 25%;
    }

    .nav-item-mobile i {
      font-size: 20px;
    }

    .nav-item-mobile.active {
      color: var(--primary);
    }

    /* Responsive Design */
    @media (min-width: 768px) {
      /* Larger tablet styles */
      body {
        padding-bottom: 0;
      }
      
      .bottom-nav-container {
        display: none;
      }
      
      .sidebar {
        display: flex;
        flex-direction: column;
      }
      
      .main-content {
        width: calc(100% - 280px);
        padding: var(--space-xl);
      }
      
      .header {
        display: none;
      }
    }

    @media (min-width: 1024px) {
      /* Desktop styles */
      .form-container {
        padding: var(--space-xl);
      }
    }
  </style>
</head>
<body>
  <div class="app-container">
    <!-- Sidebar - Desktop -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo">LM</div>
        <div class="app-name">LearnMate</div>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Menu</div>
        <a href="student_Dashboard.html" class="nav-item">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        <a href="#" class="nav-item">
          <i class="fas fa-layer-group"></i>
          <span>My Decks</span>
        </a>
        <a href="student_Dashboard.html" class="nav-item active">
          <i class="fas fa-users"></i>
          <span>Public Groups</span>
        </a>
        <a href="#" class="nav-item">
          <i class="fas fa-chart-line"></i>
          <span>Progress</span>
        </a>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Study</div>
        <a href="#" class="nav-item">
          <i class="fas fa-book-open"></i>
          <span>Learn</span>
        </a>
        <a href="#" class="nav-item">
          <i class="fas fa-pen-fancy"></i>
          <span>Write</span>
        </a>
        <a href="#" class="nav-item">
          <i class="fas fa-spell-check"></i>
          <span>Test</span>
        </a>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <a href="student_Dashboard.html" class="header-btn">
          <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="header-title">Create Group</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </header>

      <!-- Create Group Form -->
      <div class="form-container">
        <h2 class="form-title">Create New Study Group</h2>
        
        <form action="process_group.php" method="POST" enctype="multipart/form-data">
          <div class="form-group">
            <label for="group-name" class="form-label">Group Name</label>
            <input type="text" id="group-name" name="group-name" class="form-control" placeholder="Enter group name" required>
          </div>
          
          <div class="form-group">
            <label for="group-category" class="form-label">Category</label>
            <select id="group-category" name="group-category" class="form-control" required>
              <option value="" disabled selected>Select a category</option>
              <option value="Science">Science</option>
              <option value="Mathematics">Mathematics</option>
              <option value="Languages">Languages</option>
              <option value="History">History</option>
              <option value="Technology">Technology</option>
              <option value="Arts">Arts</option>
              <option value="Other">Other</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label">Group Image</label>
            <label for="group-image" class="image-upload">
              <i class="fas fa-cloud-upload-alt"></i>
              <div class="image-upload-text">Click to upload image <span>(Recommended size: 800x400px)</span></div>
              <input type="file" id="group-image" name="group-image" accept="image/*" style="display: none;">
            </label>
          </div>
          
          <div class="form-group">
            <label for="group-privacy" class="form-label">Privacy</label>
            <div style="display: flex; gap: var(--space-md);">
              <label style="display: flex; align-items: center; gap: var(--space-sm);">
                <input type="radio" name="group-privacy" value="public" checked>
                <span>Public (Anyone can join)</span>
              </label>
              <label style="display: flex; align-items: center; gap: var(--space-sm);">
                <input type="radio" name="group-privacy" value="private">
                <span>Private (Invite only)</span>
              </label>
            </div>
          </div>
          
          <button type="submit" class="btn-submit">
            <i class="fas fa-plus"></i>
            Create Group
          </button>
        </form>
      </div>
    </main>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="student_Dashboard.html" class="nav-item-mobile">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="#" class="nav-item-mobile">
        <i class="fas fa-layer-group"></i>
        <span>Decks</span>
      </a>
      
      <!-- FAB Container - stays fixed with nav -->
      <div class="fab-container">
        <button class="fab">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      
      <!-- Spacer for FAB area -->
      <div style="width: 25%;"></div>
      
      <a href="student_Dashboard.html" class="nav-item-mobile active">
        <i class="fas fa-users"></i>
        <span>Groups</span>
      </a>
      <a href="#" class="nav-item-mobile">
        <i class="fas fa-chart-line"></i>
        <span>Progress</span>
      </a>
    </nav>
  </div>

  <script>
    // Display selected image filename
    document.getElementById('group-image').addEventListener('change', function(e) {
      const fileName = e.target.files[0]?.name || 'No file selected';
      const uploadText = document.querySelector('.image-upload-text');
      uploadText.innerHTML = `Selected: <span>${fileName}</span>`;
      
      // Preview image
      if (e.target.files && e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function(event) {
          const previewIcon = document.querySelector('.image-upload i');
          previewIcon.style.display = 'none';
          
          let imgPreview = document.querySelector('.image-upload img');
          if (!imgPreview) {
            imgPreview = document.createElement('img');
            imgPreview.style.maxWidth = '100%';
            imgPreview.style.maxHeight = '150px';
            imgPreview.style.borderRadius = 'var(--radius-sm)';
            document.querySelector('.image-upload').prepend(imgPreview);
          }
          imgPreview.src = event.target.result;
        }
        reader.readAsDataURL(e.target.files[0]);
      }
    });
  </script>
</body>
</html>