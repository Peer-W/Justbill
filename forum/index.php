<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$page = $_GET['page'] ?? 'home';
$category = $_GET['category'] ?? null;
$topic = $_GET['topic'] ?? null;

$settings = getSettings();
$theme = getTheme();
$forumData = getForum();
$user = isset($_SESSION['user_id']) ? getUser($_SESSION['user_id']) : null;

// Handle new topic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$user) {
        header('Location: /client?page=login&redirect=/forum');
        exit;
    }
    
    if ($_POST['action'] === 'new_topic' && $category) {
        $topicId = uniqid('topic_');
        $forumData['topics'][$topicId] = [
            'id' => $topicId,
            'category' => $category,
            'title' => htmlspecialchars($_POST['title']),
            'content' => htmlspecialchars($_POST['content']),
            'author' => $user['id'],
            'authorName' => $user['name'],
            'created' => date('Y-m-d H:i:s'),
            'replies' => [],
            'views' => 0,
            'pinned' => false,
            'locked' => false
        ];
        saveForum($forumData);
        header('Location: /forum?topic=' . $topicId);
        exit;
    }
    
    if ($_POST['action'] === 'reply' && $topic) {
        $replyId = uniqid('reply_');
        $forumData['topics'][$topic]['replies'][$replyId] = [
            'id' => $replyId,
            'content' => htmlspecialchars($_POST['content']),
            'author' => $user['id'],
            'authorName' => $user['name'],
            'created' => date('Y-m-d H:i:s')
        ];
        saveForum($forumData);
        header('Location: /forum?topic=' . $topic . '#' . $replyId);
        exit;
    }
}

// Track views
if ($topic && isset($forumData['topics'][$topic])) {
    $forumData['topics'][$topic]['views']++;
    saveForum($forumData);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum - <?php echo htmlspecialchars($settings['siteName']); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="/<?php echo htmlspecialchars($theme['favicon'] ?? 'favicon.ico'); ?>">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-content">
            <a href="/" class="logo">
                <img src="/logo.png" alt="<?php echo htmlspecialchars($settings['siteName']); ?>" class="logo-img">
            </a>
            <div class="nav-links">
                <a href="/">Home</a>
                <a href="/kb">Knowledge Base</a>
                <a href="/forum" class="active">Forum</a>
                <a href="/client">Klantenportaal</a>
            </div>
        </div>
    </nav>

    <main class="forum-page">
        <div class="container">
            <?php if ($topic && isset($forumData['topics'][$topic])): ?>
                <?php $t = $forumData['topics'][$topic]; ?>
                <div class="forum-breadcrumb">
                    <a href="/forum">Forum</a>
                    <span>/</span>
                    <a href="/forum?category=<?php echo urlencode($t['category']); ?>"><?php echo htmlspecialchars($forumData['categories'][$t['category']]['name'] ?? $t['category']); ?></a>
                    <span>/</span>
                    <span><?php echo htmlspecialchars($t['title']); ?></span>
                </div>
                
                <div class="forum-topic">
                    <div class="topic-header">
                        <?php if ($t['pinned']): ?><span class="badge badge-primary">Vastgepind</span><?php endif; ?>
                        <?php if ($t['locked']): ?><span class="badge badge-warning">Gesloten</span><?php endif; ?>
                        <h1><?php echo htmlspecialchars($t['title']); ?></h1>
                    </div>
                    
                    <div class="forum-post">
                        <div class="post-author">
                            <div class="author-avatar"><?php echo strtoupper(substr($t['authorName'], 0, 1)); ?></div>
                            <span class="author-name"><?php echo htmlspecialchars($t['authorName']); ?></span>
                            <span class="post-date"><?php echo date('d-m-Y H:i', strtotime($t['created'])); ?></span>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($t['content'])); ?>
                        </div>
                    </div>
                    
                    <?php foreach ($t['replies'] as $reply): ?>
                    <div class="forum-post reply" id="<?php echo $reply['id']; ?>">
                        <div class="post-author">
                            <div class="author-avatar"><?php echo strtoupper(substr($reply['authorName'], 0, 1)); ?></div>
                            <span class="author-name"><?php echo htmlspecialchars($reply['authorName']); ?></span>
                            <span class="post-date"><?php echo date('d-m-Y H:i', strtotime($reply['created'])); ?></span>
                        </div>
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (!$t['locked']): ?>
                    <div class="forum-reply-form">
                        <h3>Reageren</h3>
                        <?php if ($user): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="reply">
                            <div class="form-group">
                                <textarea name="content" class="form-textarea" rows="4" placeholder="Schrijf je reactie..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Reageren</button>
                        </form>
                        <?php else: ?>
                        <p><a href="/client?page=login&redirect=/forum?topic=<?php echo urlencode($topic); ?>">Log in</a> om te reageren.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($category && isset($forumData['categories'][$category])): ?>
                <?php $cat = $forumData['categories'][$category]; ?>
                <div class="forum-breadcrumb">
                    <a href="/forum">Forum</a>
                    <span>/</span>
                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                </div>
                
                <div class="forum-category-header">
                    <div>
                        <h1><?php echo htmlspecialchars($cat['name']); ?></h1>
                        <p><?php echo htmlspecialchars($cat['description'] ?? ''); ?></p>
                    </div>
                    <?php if ($user): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('newTopicModal').classList.add('active')">Nieuw Topic</button>
                    <?php endif; ?>
                </div>
                
                <div class="forum-topics-list">
                    <div class="topics-header">
                        <span>Topic</span>
                        <span>Reacties</span>
                        <span>Weergaven</span>
                        <span>Laatste Activiteit</span>
                    </div>
                    
                    <?php 
                    $catTopics = array_filter($forumData['topics'], fn($t) => $t['category'] === $category);
                    usort($catTopics, function($a, $b) {
                        if ($a['pinned'] !== $b['pinned']) return $b['pinned'] - $a['pinned'];
                        return strtotime($b['created']) - strtotime($a['created']);
                    });
                    
                    foreach ($catTopics as $t): 
                    ?>
                    <a href="/forum?topic=<?php echo urlencode($t['id']); ?>" class="topic-row <?php echo $t['pinned'] ? 'pinned' : ''; ?>">
                        <div class="topic-info">
                            <?php if ($t['pinned']): ?><span class="badge badge-sm">Vastgepind</span><?php endif; ?>
                            <?php if ($t['locked']): ?><span class="badge badge-sm badge-warning">Gesloten</span><?php endif; ?>
                            <h3><?php echo htmlspecialchars($t['title']); ?></h3>
                            <span class="topic-author">door <?php echo htmlspecialchars($t['authorName']); ?></span>
                        </div>
                        <span class="topic-replies"><?php echo count($t['replies']); ?></span>
                        <span class="topic-views"><?php echo $t['views']; ?></span>
                        <span class="topic-date"><?php echo date('d-m-Y', strtotime($t['created'])); ?></span>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($catTopics)): ?>
                    <div class="empty-state">
                        <p>Nog geen topics in deze categorie.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- New Topic Modal -->
                <div class="modal" id="newTopicModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Nieuw Topic</h2>
                            <button class="modal-close" onclick="document.getElementById('newTopicModal').classList.remove('active')">&times;</button>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="new_topic">
                            <div class="form-group">
                                <label>Titel</label>
                                <input type="text" name="title" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label>Inhoud</label>
                                <textarea name="content" class="form-textarea" rows="6" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newTopicModal').classList.remove('active')">Annuleren</button>
                                <button type="submit" class="btn btn-primary">Topic Plaatsen</button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="forum-header">
                    <h1>Community Forum</h1>
                    <p>Stel vragen, deel kennis en help anderen</p>
                </div>
                
                <div class="forum-categories">
                    <?php foreach ($forumData['categories'] as $id => $cat): ?>
                    <a href="/forum?category=<?php echo urlencode($id); ?>" class="forum-category-card">
                        <div class="category-icon"><?php echo $cat['icon'] ?? '💬'; ?></div>
                        <div class="category-info">
                            <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                            <p><?php echo htmlspecialchars($cat['description'] ?? ''); ?></p>
                        </div>
                        <div class="category-stats">
                            <?php 
                            $topicCount = count(array_filter($forumData['topics'], fn($t) => $t['category'] === $id));
                            ?>
                            <span><?php echo $topicCount; ?> topics</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="forum-recent">
                    <h2>Recente Topics</h2>
                    <div class="forum-topics-list compact">
                        <?php 
                        $recentTopics = $forumData['topics'];
                        usort($recentTopics, fn($a, $b) => strtotime($b['created']) - strtotime($a['created']));
                        $recentTopics = array_slice($recentTopics, 0, 10);
                        
                        foreach ($recentTopics as $t): 
                        ?>
                        <a href="/forum?topic=<?php echo urlencode($t['id']); ?>" class="topic-row compact">
                            <div class="topic-info">
                                <h3><?php echo htmlspecialchars($t['title']); ?></h3>
                                <span class="topic-meta">
                                    in <?php echo htmlspecialchars($forumData['categories'][$t['category']]['name'] ?? $t['category']); ?>
                                    door <?php echo htmlspecialchars($t['authorName']); ?>
                                </span>
                            </div>
                            <span class="topic-date"><?php echo date('d-m-Y', strtotime($t['created'])); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['siteName']); ?>. Alle rechten voorbehouden.</p>
        </div>
    </footer>
</body>
</html>
