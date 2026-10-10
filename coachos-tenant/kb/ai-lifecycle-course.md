# AI Software Lifecycle — Complete Course Guide

**Course:** AI Software Lifecycle  
**Subtitle:** Build and run an autonomous AI development system entirely from your phone  
**Level:** Intermediate  
**Duration:** 15 minutes initial setup. Each step builds on the previous. Full system takes a half day to configure end-to-end.  
**Instructor:** Martien de Jong  
**URL:** https://test.prospergenics.com/courses/ai-software-lifecycle/

---

## What you will build

By the end of this course you will have a development system where:

- An AI agent reads your project documentation and knows what to build
- Multiple agents can work on separate features simultaneously without conflicts
- Every change is tracked in Git so you can see what changed and roll back if needed
- Tasks move automatically from "to do" to "in progress" to "done" as the agent works
- Deployments happen with one command, or automatically after the agent finishes
- You can open your phone, describe a feature, and have the agent research, plan, code, test and deploy it while you go about your day

You are still in control. You decide what gets built and approve the important decisions. The agents handle the implementation.

---

## Step 1: What You Will Build (Introduction)

**Goal:** Understand the system before you build it.

The AI software development system has four layers:

1. **The agent** — an AI that can read files, write code, run commands and use tools (Claude Code, Cursor, or Windsurf)
2. **The workspace** — a Git repository with documentation the agent reads on every session
3. **The task board** — a list of what needs to be done, updated by both you and the agent
4. **The phone interface** — any AI chat app (Claude, ChatGPT) connected to your task board

When you want something built, you add a task or simply say it to your phone. The agent picks it up, investigates, writes the code, creates a pull request, and deploys to staging. You review and approve. Done.

---

## Step 2: Install Your AI Coding Agent

**Goal:** Get an AI agent running on your development machine that can read and write files, run commands, and use tools.

**Recommended:** Claude Code (Anthropic)

Installation:
```
npm install -g @anthropic-ai/claude-code
```

Then run `claude` in your project directory and connect your Anthropic API key when prompted.

**What to try first:**
- Ask the agent: "What files are in this directory and what does this project do?"
- Ask it to create a simple test file and then delete it
- Watch it use tools: it reads files, runs commands, writes output

**Other options:** Cursor (cursor.sh), Windsurf (codeium.com/windsurf) — both embed an AI agent in a code editor with similar capabilities.

**Key point:** The agent can only help you if it has access to your machine. It needs to run where the code lives — on your PC, a server, or a VPS.

---

## Step 3: Give Your Agent Persistent Memory

**Goal:** Make the agent remember your project across sessions.

By default, every agent session starts fresh. The agent has no memory of previous conversations. Fix this by giving it a documentation file it reads at the start of every session.

For Claude Code, create a file called `CLAUDE.md` in your project root:

```markdown
# Project: My Website

## What this is
A WordPress site for [describe your project].

## Coding rules
- Use TypeScript, not JavaScript
- Never commit directly to main
- Always create a pull request for review

## Current priorities
- [Task 1]
- [Task 2]

## How to deploy
Run: `./deploy.sh staging`
```

Every time the agent starts, it reads CLAUDE.md and knows your project. This is the agent's persistent memory.

**What to add to CLAUDE.md:**
- What the project is and what it does
- Your coding standards and preferred tools
- How to run the project locally
- How to deploy
- Anything the agent should never do (delete the database, push to main, etc.)

---

## Step 4: Version-Control Your Agent's Knowledge

**Goal:** Track every change the agent makes, including changes to its own documentation.

Initialize Git in your project if you have not already:

```
git init
git add CLAUDE.md
git commit -m "Add agent memory file"
```

Now ask the agent to change something in CLAUDE.md. Then run:

```
git diff
```

You can see exactly what it changed, when, and why.

**Why this matters:**  
The agent will make mistakes. Sometimes it will delete something it should not, or change a rule that was working fine. With Git you can always roll back:

```
git checkout HEAD~1 -- CLAUDE.md
```

**Best practice:** Ask the agent to commit after every completed task with a clear message. Your Git history becomes a log of what was built and when.

---

## Step 5: Safe Parallel Development with Git Worktrees

**Goal:** Run multiple agents on different features simultaneously, without them interfering with each other.

**The problem:** If two agents work in the same directory and edit the same files, they will cause conflicts and corrupt each other's work.

**The solution:** Git worktrees — separate directories linked to the same repository, each on its own branch.

Create a worktree for a new feature:

```
git worktree add ../my-project-feature-login feature/login
```

Now you have two directories:
- `my-project/` — main branch, one agent
- `my-project-feature-login/` — feature/login branch, another agent

Each agent works independently. When a feature is done, create a pull request and merge it. Delete the worktree:

```
git worktree remove ../my-project-feature-login
```

**Rule of thumb:** One worktree per agent, one feature per worktree. Never let two agents work in the same directory.

---

## Step 6: Connect Your Task Manager

**Goal:** Give your agent access to your task board so it knows what to work on and can update status automatically.

Connect Claude Code to your task board via MCP (Model Context Protocol):

**For JengoWork (tasks.prospergenics.com):**  
Add to your Claude Code config (`~/.claude/settings.json`):
```json
{
  "mcpServers": {
    "jengowork": {
      "type": "http",
      "url": "https://tasks.prospergenics.com/mcp",
      "headers": { "X-Api-Key": "your-api-key" }
    }
  }
}
```

Now the agent can:
- Read tasks: "What tasks are in the backlog?"
- Start a task: "Move task 1234 to in progress"
- Complete a task: "Mark task 1234 as done"
- Create tasks: "Add a task to fix the login bug"

**From your phone:** Add a task on your phone → agent picks it up → agent builds it → agent marks it done.

You are now the product owner, not the developer.

---

## Step 7: Automate Your Deployment Pipeline

**Goal:** Let the agent deploy its work to a test environment automatically, with your approval before it reaches production.

Create a deploy script in your project:

```bash
#!/bin/bash
# deploy.sh
set -e
ENV=${1:-staging}
echo "Deploying to $ENV..."
git push origin HEAD
ssh user@your-server "cd /var/www/mysite && git pull && npm run build"
echo "Done."
```

Give the agent permission to run this script in CLAUDE.md:

```markdown
## Deployment
- Test environment: run `./deploy.sh staging` after completing a task
- Production: NEVER deploy to production without explicit approval from Martien
```

Now the agent deploys its own work to staging. You review it there. When you approve, run `./deploy.sh production` — or ask the agent to do it.

---

## Step 8: Add AI Code Review and Testing

**Goal:** Have the agent review its own code for bugs before you see it.

Before creating a pull request, ask the agent to review its own work:

```
"Review the changes you just made. Look for bugs, security issues, and anything that doesn't match our coding standards. Fix anything you find, then create a pull request."
```

The agent will:
1. Read the diff
2. Check for common bugs
3. Fix anything it finds
4. Create a pull request with a description

**Automated tests:** If your project has a test suite, add to CLAUDE.md:

```markdown
## Before creating a pull request
1. Run `npm test` and fix any failures
2. Run `npm run lint` and fix any warnings
3. Then create the pull request
```

The agent follows these instructions every time. You only see pull requests that already passed tests.

---

## Step 9: Set Up Mobile Access

**Goal:** Connect your phone to the system so you can give instructions and receive updates from anywhere.

**Option 1: Claude app (simplest)**  
Install Claude on your phone. Log into claude.ai. Configure Claude Code's MCP connections on your phone — it will have access to the same task board as the agent on your computer.

**Option 2: ChatGPT with custom actions**  
Install ChatGPT on your phone. Create a custom GPT that connects to your task board API. You can then ask it to create tasks, check status, or trigger deployments.

**What you can do from your phone:**
- "What is being worked on right now?"
- "Add a task: the contact form sends to the wrong email address"
- "Review everything that is waiting for testing"
- "The login feature looks good on staging — deploy to production"

Your phone is now the command interface for your entire development operation.

---

## Step 10: Run Your Entire Lifecycle From Your Phone

**Goal:** Bring it all together — from idea to deployed feature, managed entirely from your phone.

**Full workflow example:**

1. You notice a bug on your website. You open Claude on your phone.
2. You say: "The contact form is sending emails to info@example.com instead of my real address. Please fix this."
3. The agent creates a task on the board, investigates the codebase, finds the issue, creates a worktree for the fix, writes the fix, runs tests, deploys to staging.
4. You get a notification: "Fix is on staging. Here is the link."
5. You check it on your phone. It works.
6. You say: "Deploy to production."
7. Done.

You did not open a code editor. You did not look at a single line of code. You described what you wanted, approved the result, and the agents handled everything in between.

**This is the AI software lifecycle.**

---

## What to build next

Once your system is running, consider these extensions:

- **Multi-agent fleet:** Set up a pool of worktrees so multiple features can be built simultaneously
- **Approval inbox:** A small web app that shows you pending pull requests and lets you approve with one tap from your phone
- **Scheduled agents:** A cron job that runs the agent every night to check for outdated dependencies, security updates, or broken links
- **Knowledge base:** A searchable archive of your project decisions, architecture, and lessons learned — loaded into the agent's context automatically

The ProsperGenics guide can answer questions about any of these. Just ask.
