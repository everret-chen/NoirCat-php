# 每天开始前
git checkout dev
git pull origin dev

# 开发新功能
git checkout -b feature/forum-post
# 写代码...
git add .
git commit -m "feat(forum): add post list page"
git push origin feature/forum-post

# 合并回 dev（本地）
git checkout dev
git merge feature/forum-post
git push origin dev

# 删除已合并的分支
git branch -d feature/forum-post