# Cross Duel (mod_crossduel)

Cross Duel is a two-player, turn-based crossword activity module for Moodle.

It supports both single-player practice and pseudo-real-time multiplayer gameplay, where learners take turns solving crossword clues on a shared board.

---

## ✨ Features

- Automatic crossword layout generation from a simple word list
- Two-player “ping-pong” gameplay (pseudo-real-time)
- Teacher preview and approval of generated layouts
- Configurable reveal percentage and pass threshold
- Gradebook integration
- Works asynchronously — players do not need to be online at the same time
- Safe single-player mode for practice and testing

---

## 📝 Authoring Content

Teachers define the crossword using a simple format:

word|clue

Example:
algorithm|A step-by-step procedure
variable|A named value that can change
loop|A repeated sequence of instructions

### Rules
- One entry per line
- Use a single | to separate word and clue
- Blank lines are ignored
- Recommended maximum: 50 entries
- Simple single words work best for layout generation

---

## 🧑‍🏫 Teacher Workflow

1. Create activity and enter word list  
2. Open Preview  
3. Review generated layout  
4. Approve layout  
5. Students can now play  

---

## 🎮 Gameplay

- Players take turns solving words
- Each correct answer reveals part of the shared crossword
- Progress is saved between sessions
- The game ends when all words are solved or conditions are met

---

## ⚙️ Installation

1. Copy the plugin to:
   /mod/crossduel

2. Visit:
   Site administration → Notifications

3. Complete installation

---

## 🧪 Compatibility

- Moodle 4.4+  
- Moodle 5.x (tested on 5.1.x)

---

## 🔐 Privacy

This plugin stores user activity data including:
- attempts
- answers
- game participation
- moves

It implements the Moodle Privacy API and supports data export and deletion.

---

## ⚠️ Notes for Reviewers

- Uses pseudo-real-time polling for multiplayer gameplay  
- AJAX currently implemented via view.php routing  
- Designed to migrate to External Services in future iteration  

---

## 🛠 Development Notes

- Built with a cPanel-first workflow (no CLI required)
- Designed to be understandable and modifiable by non-specialist developers
- Emphasis on clean, incremental compliance with Moodle standards

---

## 📄 License

GNU GPL v3 or later  
http://www.gnu.org/copyleft/gpl.html

---

## 👤 Author

Johan Venter  
johan@myfutureway.co.za

---

## 🚀 Roadmap (optional future improvements)

- Migration to External Services (AJAX modernization)
- AMD + Mustache UI layer
- Enhanced layout generation heuristics
- Real-time (WebSocket) option
