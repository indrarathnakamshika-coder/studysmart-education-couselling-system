/* StudySmart rule-based student assistant UI */
(function () {
  var root = document.getElementById("student-chatbot");
  if (!root) return;

  var toggleBtn = document.getElementById("student-chatbot-toggle");
  var closeBtn = document.getElementById("student-chatbot-close");
  var panel = document.getElementById("student-chatbot-panel");
  var messages = document.getElementById("student-chatbot-messages");
  var form = document.getElementById("student-chatbot-form");
  var input = document.getElementById("student-chatbot-input");

  if (!toggleBtn || !closeBtn || !panel || !messages || !form || !input) return;

  var openedOnce = false;
  var quickPrompts = [
    "What can I study after O/L?",
    "Explain Level 1 to Level 4",
    "How do recommendations work?",
    "How do I apply for a course?",
    "How does payment work?",
    "Where do I update my profile?",
  ];

  function setOpen(open) {
    root.classList.toggle("is-open", open);
    panel.setAttribute("aria-hidden", open ? "false" : "true");
    toggleBtn.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      setTimeout(function () {
        input.focus();
        scrollToBottom();
      }, 80);
      if (!openedOnce) {
        openedOnce = true;
        addBotMessage(
          "Hi! I'm your StudySmart assistant. I can help you choose courses, understand your options, and guide you through the system."
        );
        renderQuickChips();
      }
    }
  }

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function bubble(text, who) {
    var row = document.createElement("div");
    row.className = "student-chatbot__row student-chatbot__row--" + who;

    var msg = document.createElement("div");
    msg.className = "student-chatbot__bubble student-chatbot__bubble--" + who;
    msg.textContent = text;

    row.appendChild(msg);
    messages.appendChild(row);
    scrollToBottom();
    return row;
  }

  function addBotMessage(text) {
    bubble(text, "bot");
  }

  function addUserMessage(text) {
    bubble(text, "user");
  }

  function addTyping() {
    var row = document.createElement("div");
    row.className = "student-chatbot__row student-chatbot__row--bot";
    row.id = "student-chatbot-typing";

    var msg = document.createElement("div");
    msg.className = "student-chatbot__bubble student-chatbot__bubble--bot";

    var dots = document.createElement("span");
    dots.className = "student-chatbot__typing";
    dots.innerHTML = "<i></i><i></i><i></i>";

    msg.appendChild(dots);
    row.appendChild(msg);
    messages.appendChild(row);
    scrollToBottom();
  }

  function removeTyping() {
    var el = document.getElementById("student-chatbot-typing");
    if (el) el.remove();
  }

  function renderQuickChips() {
    var existing = document.getElementById("student-chatbot-chips");
    if (existing) existing.remove();

    var wrap = document.createElement("div");
    wrap.id = "student-chatbot-chips";
    wrap.className = "student-chatbot__chips";

    var label = document.createElement("p");
    label.className = "student-chatbot__chips-label";
    label.textContent = "Quick questions";
    wrap.appendChild(label);

    var list = document.createElement("div");
    list.className = "student-chatbot__chips-list";

    quickPrompts.forEach(function (prompt) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "student-chatbot__chip";
      btn.textContent = prompt;
      btn.addEventListener("click", function () {
        askAssistant(prompt);
      });
      list.appendChild(btn);
    });

    wrap.appendChild(list);
    messages.appendChild(wrap);
    scrollToBottom();
  }

  async function askAssistant(question) {
    addUserMessage(question);
    addTyping();
    try {
      var res = await fetch("chatbot_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: question }),
      });
      if (!res.ok) throw new Error("Network response not ok");
      var data = await res.json();
      removeTyping();
      addBotMessage(
        data && typeof data.reply === "string" && data.reply.trim() !== ""
          ? data.reply.trim()
          : "I can still help with course paths, profile setup, recommendations, applications, and payments. Try asking one of those topics."
      );
    } catch (err) {
      removeTyping();
      addBotMessage(
        "I couldn't connect right now. Please try again in a moment. In the meantime, you can open Profile, Recommendations, Applications, or Payments from the left menu."
      );
    }
  }

  toggleBtn.addEventListener("click", function () {
    setOpen(!root.classList.contains("is-open"));
  });

  closeBtn.addEventListener("click", function () {
    setOpen(false);
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var value = (input.value || "").trim();
    if (!value) return;
    input.value = "";
    askAssistant(value);
  });

  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });
})();
