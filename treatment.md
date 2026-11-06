# Treatment — Bắc Du (Travel Planner) Landing Page
**Edition:** Cinematic UI 2.0 — Director's Cut

---

## 1. Development — The Brief, Logline & Audience Arc

- **Product Truth:** **Bắc Du (Travel Planner)** là nền tảng tham khảo lịch trình 12 tháng và lập kế hoạch du lịch miền Bắc Việt Nam. Kết hợp lịch trình mẫu chuẩn chỉnh từ Ban biên tập (đầy đủ điểm tham quan, quán ăn, lưu trú, dự toán chi phí không tính trùng) với góc nhìn thực tế đã kiểm duyệt từ Cộng đồng. Người dùng chọn tháng đẹp nhất trong năm, xem mẫu, và sao chép thành chuyến đi riêng chỉ với 1 cú nhấp chuột để tự do tùy chỉnh điểm đến, ngân sách và bản đồ Leaflet.
- **Primary Action:** Chọn tháng khởi hành (1–12), khám phá lịch trình mẫu chuẩn mùa (`templates.php?month=X` / bộ lọc nhanh), hoặc tự tạo chuyến đi mới (`trips.php?action=new`).
- **Logline:** Một người mê xê dịch đứng giữa hàng chục tab rời rạc vì không biết tháng này miền Bắc nơi nào đang vào mùa đẹp nhất, xoay chiếc **La bàn Thời tiết 12 Tháng** để thấy thung lũng hoa mận Nà Ka và lộ trình 3 ngày 2 đêm kèm dự toán chi tiết hiện ra ngay trước mắt, sẵn sàng sao chép thành chuyến đi riêng chỉ bằng một cú nhấp.
- **Audience Arc:**
  - *Arrives believing:* Lên kế hoạch đi miền Bắc tự túc mất hàng giờ tìm kiếm, dễ đi lệch mùa hoa/mây và rối tung giữa chi phí ăn-ở-di chuyển.
  - *Leaves believing:* Mỗi tháng miền Bắc đều có tọa độ rực rỡ nhất đã được Ban biên tập và Cộng đồng kiểm chứng — và tôi có thể biến nó thành lịch trình cá nhân chuẩn ngân sách trong 30 giây.
- **Controlling Idea:** Đi đúng mùa quan trọng hơn đi nhiều nơi; một kế hoạch rõ ràng làm nên chuyến đi tự do.

---

## 2. Casting the Director

### Casting Session (Three Candidates)

1. **Bi Gan — *Kaili Blues* (*Lu Bian Ye Can*, 2015, DP: Wang Tianxing)**
   - *Mechanism:* Chuyển động máy quay xuyên qua sương mù núi đá vôi và đường hầm như một chuyến tàu liên tục; đồng hồ và thời gian xuất hiện trên tường.
   - *Signature shot:* Máy trượt ngang từ trong khoang tàu tối ra thung lũng sương mù xanh thẫm.
   - *Risk & Wrong for:* Nhịp phim quá mơ màng, phi tuyến tính; sai cho một công cụ cần sự minh bạch về ngân sách (VNĐ) và lịch trình theo ngày. -> *Set aside.*
2. **Hirokazu Kore-eda — *Our Little Sister* (*Umimachi Diary*, 2015, DP: Mikiya Takimoto)**
   - *Mechanism:* Đo đếm một năm bằng mùa vụ tự nhiên (mùa hoa mận, mùa mưa, mùa thu hoạch), khung hình tĩnh qua hiên nhà gỗ, ánh sáng tự nhiên dịu nhẹ.
   - *Signature shot:* Cận cảnh bàn tay chuẩn bị đồ ăn theo mùa trước khung cửa mở ra vườn.
   - *Risk & Wrong for:* Quá tĩnh tại trong không gian gia đình, thiếu độ hùng vĩ của đèo Mã Pí Lèng, thác Bản Giốc hay đỉnh Fansipan. -> *Set aside.*
3. **Trần Anh Hùng — *The Vertical Ray of the Sun* (*Mùa Hè Chiều Thẳng Đứng*, 2000, DP: Mark Lee Ping-bin 李屏賓, PD: Benoît Barouh) — WINNER**
   - *Mechanism:* Ánh sáng tự nhiên miền Bắc Việt Nam chiếu qua khung cửa chớp gỗ và tán lá xanh thẫm tạo chiều sâu nhiều lớp; máy quay dolly ngang chậm rãi của Mark Lee Ping-bin lướt qua các khung cửa sổ địa lý (Hà Nội – Hạ Long – Tây Bắc), kết hợp sắc xanh ngọc bích núi rừng với sắc vàng hổ phách ấm áp của gỗ và nắng mùa màng.
   - *Signature shot:* Khung cảnh mở ra từ khe cửa chớp tối màu sang thung lũng ngập nắng và sương sớm, trong khi chiếc La bàn Địa dư – Mùa vụ xoay theo từng tháng.
   - *Why a competitor cannot use this:* Gắn chặt với địa lý khí hậu 4 mùa – 12 tháng của miền Bắc Việt Nam và cấu trúc dữ liệu 3 nhóm (Tham quan · Ăn uống · Lưu trú) của Bắc Du.

### Six Extracted Mechanisms (Trần Anh Hùng & Mark Lee Ping-bin)
1. **Aperture Framing (Khung trong khung):** Các cảnh mở ra qua khung cửa chớp / khung sổ địa dư (`window-aperture`) trước khi mở rộng toàn khung hình [S].
2. **Lateral Dolly Rail (Cú máy trượt ngang):** Hành trình 4 bước và dải 12 tháng trượt ngang mượt mà (`kit/reel.js` horizontal track) với tiền cảnh và hậu cảnh tách lớp tốc độ khác nhau [S].
3. **Botanical Jade vs Warm Lacquer Split-Tone:** Bóng đổ mang sắc xanh rừng thẳm (`#0d261d`), vùng sáng mang sắc vàng nắng hổ phách (`#f6deb3`) và giấy dó ngà (`#f4efe4`) [S].
4. **Sensory Trio Staging (Tam giác Giác quan):** Địa điểm không xếp thành lưới vô hồn mà được dàn cảnh theo 3 trải nghiệm vật lý của chuyến đi: *Cảnh sắc (Tham quan)* — *Hương vị (Ăn uống)* — *Hiên nhà (Lưu trú)* [S].
5. **Time & Season Discipline:** Mỗi cảnh đều đóng dấu thời gian – tọa độ vĩ độ Bắc – nhiệt độ/mùa vụ rõ ràng như những chương ngày nắng/mưa trong phim [S/I].
6. **Breathing Holds Between Motion:** Xen kẽ các phân cảnh chuyển động máy quay (Hero, La bàn 12 tháng, Cuộn phim 4 bước) với các phân cảnh tài liệu tĩnh lặng (`data-flow`) để người đọc tra cứu giá tiền, lịch trình và review thực tế dễ dàng [S].

### Difference from Filmography
So với các ví dụ mẫu trong skill (`~/.cinematic-ui/filmography.md`), bản dựng này khác biệt trên **7/8 trục**:
- **Director/Film:** Trần Anh Hùng / *Mùa Hè Chiều Thẳng Đứng* (2000)
- **Medium:** Nhiếp ảnh & tư liệu thực địa miền Bắc kết hợp bản vẽ đồng bộ La bàn Địa dư SVG (`grade.js` + `reel.js` + `type-fx.js`, không lạm dụng 3D WebGL vô nghĩa)
- **Form:** Document with set pieces (giữ trọn vẹn 100% dữ liệu PHP động từ CSDL)
- **Skeleton:** Wave (Cold open trên sương mù & cửa sổ Bắc Bộ → Đỉnh cao tại La bàn 12 tháng & Cuộn phim 4 bước → Lắng đọng ở Góc Cộng đồng → Kết nối lại ở Bản đồ Khởi hành)
- **Lead:** **Chiếc La bàn Thời tiết & Sổ Địa dư Bắc Bộ (The Northern Almanac Compass)** biến đổi qua 6 trạng thái từ lúc mở trang đến cuối trang
- **Interaction:** Xoay/chọn trực tiếp vòng **La bàn 12 Tháng** (và Bộ lọc Lịch trình Nhanh) làm thay đổi tức thì trạng thái mùa, lý do theo mùa, tọa độ gợi ý và liên kết hành trình trên trang
- **Instruments:** Thanh thước Kinh độ/Vĩ độ Bắc Bộ (`20°15'N → 23°22'N`), Kim chỉ Tháng hiện hành, và Bộ đếm Cảnh (`SC. 01/06`).

---

## 3. Medium, Grade, Art Direction & Poster

### Medium Decision
- **Medium:** **Observed Photography & Field Almanac Graphics** (Nhiếp ảnh thực địa miền Bắc kết hợp Đồ họa Sổ Địa dư & La bàn Khí hậu trên nền DOM + `kit/reel.js` + `kit/grade.js` + `kit/type-fx.js`).
- **Why this medium:** Cơ chế của *Mùa Hè Chiều Thẳng Đứng* nằm ở ánh sáng tự nhiên xuyên qua kiến trúc và sắc độ thực của thiên nhiên Bắc Bộ (sương mù Tà Xùa, hoa mận Nà Ka, nước xanh Nho Quế, lúa vàng Tam Cốc) — thứ mà du khách cần nhìn thấy bằng ảnh thật được chỉnh màu điện ảnh chứ không phải khối 3D giả tạo.
- **Why not 3D:** Du khách quyết định đi Mộc Châu, Hà Giang hay Ninh Bình vì vẻ đẹp thật của cảnh quan, món ăn và homestay; dựng khối 3D xoay tròn ở Hero sẽ vi phạm quy tắc *Lazy Cut* của skill.

### GRADE Block
```
GRADE        The Vertical Ray of the Sun (2000) · Hanoi & Ha Long sequence · unmeasured (starting values from researched palette in research/vertical-ray-of-the-sun.md; stills requested in asset call)
SOURCE       Hard vertical tropical sunlight filtered through dark-green wooden shutters and courtyard foliage onto warm limewashed ochre walls and lacquerware [S]
BLACKS       Deep forest-lacquer shadow, slightly lifted with humid jade haze — value: #0b1914
HIGHLIGHTS   Warm sunlit silk & filtered afternoon cream — halation colour #e08b3e · bloom only on sunlit water/mist highlights
HUES         ground #0b1914 (set pieces) / #f4efe4 (almanac reading paper) · ink #16241d / #f5eedc · accent #d97706 (Saffron Amber Sun, ≤ 4% share) · secondary #156045 (Jade Karst) · BANNED hues: neon magenta (#ff00ff), cyber cyan (#00ffff), purple-blue SaaS gradients
GRAIN        Fine 35mm Eastmancolor film texture — amount 0.035
TIME/FORMAT  Widescreen 1.85:1 framing inside architectural shutter apertures; smooth lateral dolly tracks
LOOK         { contrast: 1.12, saturation: 1.18, temperature: 0.18, tint: -0.08, lift: 0.02, split: 0.38, shadows: '#0e291f', highlights: '#f7dfb9', grain: 0.035, vignette: 0.22, halation: 0.18, halationColor: '#e08b3e', bloom: 0.14, fade: 0.02 }
ARC          Opens in early-morning highland mist & deep jade shutter shade (#0b1914); opens into warm sunlit almanac parchment (#f4efe4) for the 12-Month Compass and 3-Group Location Archive; returns to evening golden-hour lacquer (#0e1f18) for the Community Chronicles & Final Departure Call
COSTUME      Conical hats (nón lá) clip-art, lotus vector stamps, fake bamboo borders, tourist agency stock badges: refused
```

### ART Block & UI Map
```
ART          The Vertical Ray of the Sun (2000) · Benoît Barouh (PD), Nguyễn Dân Nam & Vũ Huy (Art Directors) · sources [S]/[W]/[I]
SHAPE        Nested architectural apertures (cửa chớp / tall shuttered windows and courtyard colonnades) opening onto deep horizontal landscapes [S]
MATERIAL     Weathered dark-green timber, warm limewashed stone-paper (#f4efe4), brass compass hairlines, stamped ticket/almanac slips [S/I]
ORNAMENT     Hairline latitude/longitude ticks (20°N–23°N) and seasonal seal marks along frame borders only; never behind reading copy [I]
ERA          Contemporary Northern Vietnam layered over early-20th-century Indochine architectural proportions; display serif with high-contrast strokes paired with clean Vietnamese geometric grotesk [S/I]
DENSITY      Alternates: Scene 1 (Hero Aperture) sparse & atmospheric → Scene 2 (12-Month Almanac & Templates) rich interactive studio → Scene 3 (4-Step Lateral Filmstrip) kinetic & focused → Scene 4 (Locations Archive) structured 3-column field index → Scene 5 (Community Journals) intimate editorial → Scene 6 (Final Departure) monumental & quiet
GRAPHICS     Field Almanac & Kilometer-Zero (Km0) expedition slips: coordinates, month tags, seasonal reasons, and verified price ledgers [I]
UI MAP       
  - Frame: Bordered by 1px brass/jade rule with live Northern latitude readout (20°15'N — 23°22'N) and Scene slate
  - Grid: Asymmetric 12-column editorial grid with vertical hairline dividers
  - Panels & Cards: Almanac Field Slips (sharp 4px corners with notched ticket-stub header, coordinate stamp, seasonal reason strip, and bottom price ledger — zero generic rounded-4 Bootstrap cards)
  - Buttons: Stamped Expedition Seals — rectangular with chamfered/notched corner or solid amber-sun bar that illuminates on hover
  - Dividers: 1px ruled horizon lines with kilometer/coordinate ticks
  - Forms: The Quick Itinerary Finder staged as a tactile "Bảng Điều Phối Hành Trình (Dispatch Console)" integrated into the opening set piece
  - Footer: End-credits billing block + chirashi verso specification sheet (preserving Demo Accounts & Season links)
  - Imagery: Graded through kit/grade.js SVG filter (warm sunlight highlights + deep jade shadows), framed in shuttered apertures
COSTUME      Fake bamboo frames, lotus clip-art, generic Bootstrap pills, floating gradient blobs: refused
POSTER       Front-and-back Japanese Chirashi + Windowed Aperture grammar: image-led atmospheric front with the Almanac Compass and Dispatch Console; dense, structured field-manual verso for templates and locations
```

---

## 4. Casting, Locations & The Asset Call

### The Lead & Its 6 Visible States
- **Lead Actor:** **La Bàn Thời Tiết & Sổ Địa Dư Bắc Bộ (`#almanacLead` — The Northern Seasonal Compass & Coordinate Rose)** — một cấu trúc đồ họa SVG cơ khí tinh xảo gồm vòng kinh vĩ độ miền Bắc (20°N–23°N), 12 khắc tháng, kim chỉ phương Bắc và vòng khẩu độ ánh sáng.
- **Product's Verb:** **Định vị mùa đẹp nhất & Lập lộ trình (Locate the season → Map the route → Lock the budget).**
- **6 Visible States Across the Page:**
  1. **State 1 (Loader / Shot 1 — Calibration):** Kim la bàn xoay từ `00°` định vị về vĩ độ của tháng đang chọn (`Tháng <?= $selectedMonth ?>`), vẽ vòng khẩu độ mở ra khung cảnh sương sớm miền Bắc.
  2. **State 2 (Scene 1 — Hero Aperture):** Nằm ở trung tâm khẩu độ ánh sáng bình minh, phản hồi theo góc nghiêng con trỏ và hiển thị tọa độ + mùa vụ hiện tại (`<?= $monthInfo['name'] ?> · <?= $monthInfo['season'] ?>`).
  3. **State 3 (Scene 2 — The 12-Month Wheel Interactive Set Piece):** Mở rộng thành **Bánh Xe Thời Tiết 12 Tháng** nơi người dùng nhấp/xoay từng tháng (Tháng 1 → Tháng 12) để xem trước tức thì đặc trưng mùa, điểm đến nổi bật và lọc danh sách Lịch trình mẫu chuẩn mùa.
  4. **State 4 (Scene 3 — The 4-Step Expedition Reel):** Biến thành **Trục Cuộn Phim Lộ Trình (Route Sprocket & Path Line)** dẫn mắt người xem trượt ngang qua 4 bước: *Chọn tháng → Sao chép 1 chạm → Quản lý ngân sách không tính trùng → Bản đồ Leaflet & Chia sẻ*.
  5. **State 5 (Scene 4 & 5 — Field Coordinate Stamp):** Thu gọn thành con dấu tọa độ địa lý & kiểm duyệt thực tế bên cạnh 6 Địa điểm tiêu biểu (Tham quan · Ăn uống · Lưu trú) và 3 Nhật ký Cộng đồng.
  6. **State 6 (Scene 6 — Final Image / Km0 Milestone):** Hội tụ ở trung tâm khung hình cuối cùng như **Cột Mốc Km0 Khởi Hành**, khóa kim chỉ hướng Bắc vào nút hành động chính (*Tự tạo chuyến đi mới* / *Khám phá lịch trình*).

### Asset Call Status
- **Supplied & Verified in Project:**
  - Toàn bộ dữ liệu thật từ CSDL `travel_planner.sql`: 12 tháng (`get_months_catalog()`), 8 tỉnh miền Bắc, 8 lịch trình mẫu (`itinerary_templates`), 22 địa điểm thực tế (`locations` kèm tọa độ GPS, giá vé/phòng/ăn uống, lưu ý theo mùa), và các bài review cộng đồng (`experience_posts`).
  - Ảnh nhiếp ảnh miền Bắc từ CSDL + video sương sớm bản quyền mở CC0 (`assets/kit/grade-assets/fog.mp4` & `fog.jpg` của Alabama Extension).
- **Open Asset Call (Optional upgrades user can supply later):**
  1. 6–12 ảnh still gốc từ phim *Mùa Hè Chiều Thẳng Đứng* (2000) nếu muốn đo lại `grade.json` bằng `grade-from-stills.mjs` (hiện đang dùng thông số khởi đầu đã nghiên cứu từ `research/vertical-ray-of-the-sun.md`).
  2. Ảnh/video quay thực tế riêng của nhóm đồ án tại Mộc Châu, Hà Giang, Ninh Bình (nếu muốn thay cho ảnh Unsplash trong CSDL).

---

## 5. The Award Pass

```
WORLD OBJECT   Sổ Địa Dư & Phiếu Lộ Trình Bắc Bộ (Field Almanac Slip with notched corners, latitude ticks, and seasonal stamp)
LEAD STATES    6 states: (1) Loader Calibration → (2) Hero Aperture Rose → (3) Interactive 12-Month Season Dial → (4) 4-Step Lateral Route Track → (5) GPS Field Verification Seal → (6) Km0 Milestone Lock
LOADER         Shot 1: Vòng la bàn Bắc Bộ khắc từng vĩ độ từ 20°00'N đến 23°22'N và mở khẩu độ cửa chớp vào cảnh sương sớm miền Bắc (tự động hoàn tất mượt mà, không chặn người dùng)
CUT MATERIAL   Shutter-slat iris wipe (cửa chớp mở ngang/dọc) giữa các set piece và horizon-line push sang các cảnh đọc tài liệu (flow scenes)
ACCENT         Saffron Amber Sun (#d97706 / #f59e0b), nghĩa là "Đúng mùa / Khởi hành ngay", ngân sách ≤ 4% khung hình
TYPE           Cormorant Garamond / Playfair Display (Display Serif) + Be Vietnam Pro / Plus Jakarta Sans (Text Sans) + JetBrains Mono (Coordinates/Prices) · Switch device: chữ nghiêng Serif có dấu ngoặc tọa độ cho tên mùa/địa danh · Display ở ngưỡng cực đại clamp(3.2rem, 8.2vw, 7.6rem)
INSTRUMENTS    4 fixed marks: (1) Vĩ độ Bắc Bộ biến thiên theo cuộn trang (20°15'N → 23°22'N), (2) Nhãn Cảnh & Mùa hiện hành (SC.01–06 · THÁNG <?= $selectedMonth ?>), (3) Thanh tiến trình cuộn dạng thước đo hải lý, (4) Nút bật/tắt hiệu ứng điện ảnh hoặc nhảy nhanh tới Bộ lọc
VERB           Xoay/chọn Tháng khởi hành trên La bàn 12 Tháng để đối chiếu mùa đẹp nhất và đóng dấu lộ trình
TRIGGERS       (1) Entry intro (Loader & Hero aperture), (2) Scroll-scrubbed (`reel.js` cho Hero zoom, 4-Step lateral rail, Final Km0 convergence), (3) Click/Tap & Keyboard (Bánh xe 12 tháng + Bộ lọc nhanh + Tab lọc Nhóm địa điểm), (4) Hover/Focus (Lật xem lưu ý mùa vụ & kính lúp tọa độ trên thẻ địa điểm)
TYPE CAST      Signature move: `blur-resolve` (tiêu đề chính hiện ra từ màn sương sớm Tây Bắc) + Type department: `slit-scan` (thẻ chương chuyển cảnh), `odometer` (con số 12 tháng & ngân sách tham khảo), `read-fill` (tuyên ngôn mở đầu hành trình), `select-box` (khung chọn mùa đẹp nhất)
UX PROOF       Skip-to-content link · visible 2px amber focus ring · full keyboard support on 12-month dial & filter tabs · reduced-motion cut (`--reduced`) preserves full grade and all PHP data · Back/Reset filter link
RHYTHM         Scene 1 (Pinned Set Piece: Hero + Dispatch Console) → Scene 2 (Flow Studio: 12-Month Wheel + PHP Monthly Templates) → Scene 3 (Pinned Set Piece: 4-Step Lateral Filmstrip) → Scene 4 (Flow Archive: 3-Group Locations + Instant Filter) → Scene 5 (Flow Editorial: Community Reviews) → Scene 6 (Pinned Set Piece: Final Km0 Call to Action). Mobile gets portrait poster framing and horizontal swipeable month strip.
```

---

## 6. Storyboard — The Shot List

| #, slug | Beat & Belief | Copy (Core Headlines & PHP Data) | Shot & Lens | Camera (`reel.js`) | Blocking & Audience's Part | Light / Colour | Build & Asset | Cut Out & Eye-Trace | Length & Energy | Money Frame | Mobile Cut |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **1 · `arrival`**<br>EXT. HIGHLAND MIST — DAWN | **Cold Open & Dispatch:** Miền Bắc mỗi tháng có một tọa độ đẹp nhất; bạn có thể tra cứu ngay tại khung đầu tiên. | Badge: `NỀN TẢNG DU LỊCH MIỀN BẮC THEO 12 THÁNG`<br>H1: `Tháng này đi đâu ở miền Bắc? Chọn mùa đẹp nhất, lên lịch trình ngay.`<br>+ Form **Bộ tìm kiếm lịch trình nhanh** (Tháng 1–12, Tỉnh/Thành, Thời lượng, Đang hot Mộc Châu 3N2Đ) | WS → MS, eye level, 35mm through shutter aperture | Intro opens shutter aperture; scroll gently pushes frame scale `1 → 1.06` while compass rotates `0° → 45°` | Khẩu độ cửa chớp mở ra sương sớm (`fog.mp4` + ảnh cao nguyên); bên phải là **Bảng Điều Phối Hành Trình** hoạt động thật (`GET templates.php`) | Low-key jade `#0b1914`, warm rim `#f6deb3`, amber CTA `#d97706` | DOM aperture + `fog.mp4` graded via `grade.js` + SVG `#almanacLead` + `blur-resolve` on H1 | Horizon push into warm almanac paper; eye rests on Month selector → lands on 12-Month Dial | `--length: 190` (Pinned set piece)<br>Energy: **8** | `data-still="0.35"` (Full headline + Dispatch console clearly legible) | Stacks poster-style: title + CTA in top 55%, Dispatch console below, unpinned on short viewports |
| **2 · `almanac`**<br>INT. ALMANAC STUDIO — DAY | **The 12-Month Turn (Audience's Part):** Chọn bất kỳ tháng nào để thấy ngay vùng đất đang vào độ rực rỡ nhất và các lịch trình mẫu chuẩn mùa. | Eyebrow: `Bốn mùa miền Bắc`<br>H2: `Chọn tháng khởi hành — Xem gợi ý chuẩn mùa`<br>+ 12 Tháng (`$monthsCatalog`) + Kết quả Lịch trình mẫu của `<?= $monthInfo['name'] ?>` (`$monthTemplates`: ảnh, thời lượng, điểm đến, vì sao hợp tháng, ngân sách tham khảo VNĐ/người) | Overhead / Planimetric table, 50mm | Flow scene (`data-flow`) so reader can inspect every month & template card at their own pace | **Audience's Part:** Nhấp hoặc dùng phím mũi tên trên **La bàn 12 Tháng** để xem trước tức thì thông tin mùa/điểm đến và chuyển tháng (`index.php?month=X#monthly-recommendations`) | Warm sunlit almanac paper `#f4efe4`, deep jade ink `#14261c`, amber active seal `#d97706` | Interactive 12-Month Almanac Wheel/Grid + PHP `$monthTemplates` Field Slips + `odometer` budget counter | Shutter-slat wipe (`data-cut`) into dark lacquer filmstrip; eye at bottom CTA meets Step 01 on the left | `data-flow`<br>Energy: **7** | All 12 months + active month's itinerary templates framed like an expedition dossier | 12-Month grid becomes a crisp 3-col / horizontal tactile strip; cards full-width |
| **3 · `chronicle`**<br>INT. DARKROOM REEL — DUSK | **The Plan (How It Works):** Từ cảm hứng đến chuyến đi thực tế chỉ qua 4 bước liền mạch, không tính trùng chi phí. | H2 (`read-fill`): `Từ cảm hứng theo mùa đến kế hoạch độc lập trong 4 bước`<br>1. `1. Chọn tháng & Xem mẫu`<br>2. `2. Sao chép thành chuyến đi riêng`<br>3. `3. Tùy chỉnh & Quản lý ngân sách`<br>4. `4. Xem Bản đồ & Chia sẻ` | WS lateral tracking dolly, 40mm | Pinned horizontal rail (`--length: 260`): camera trucks right across the 4 steps as the route line draws across Northern coordinates | Khi cuộn trang, dải 4 bước trượt ngang qua khung hình như cú máy dolly của Mark Lee Ping-bin; kim lộ trình nối từng trạm từ `01` đến `04` | Deep lacquer `#0d1f18`, brass route line `#e09f3e`, cream cards `#f6f1e6` | `kit/reel.js` horizontal `.track` + SVG elevation profile drawing across the 4 stations | Push out to sunlit Field Archive; eye follows route line right → drops into Location filter bar | `--length: 250`<br>Energy: **9** (Climax of motion) | `data-still="0.5"` (All 4 steps visible in staggered panoramic cadence) | Converts to vertical timeline with connecting route line so nothing overflows horizontally |
| **4 · `locations`**<br>EXT. NORTHERN ARCHIVE — AFTERNOON | **Evidence (3 Standardized Groups):** Mọi địa điểm Tham quan, Ăn uống, Lưu trú đều có giá tham khảo, lưu ý mùa vụ, tọa độ GPS và nút lưu yêu thích. | Eyebrow: `Dữ liệu chuẩn hóa 3 nhóm`<br>H2: `Điểm tham quan, Quán ngon & Lưu trú chọn lọc`<br>+ Nút lọc `Tham quan`, `Ăn uống`, `Lưu trú`, `Tất cả` + 6 thẻ `$featuredLocations` (kèm form CSRF `favorites.php`, sao đánh giá, giá tham khảo, link Google Maps & Chi tiết) | MS 3-column field index, eye level, 50mm | Flow scene (`data-flow`) with interactive client-side lens filter (`All / Tham quan / Ăn uống / Lưu trú`) + direct links to `locations.php` | Hovering a location slip reveals its coordinates & seasonal badge; clicking category pills filters the wall smoothly or navigates to `locations.php?type=...` | Warm stone paper `#efe9dc`, category colour seals (Jade `#0f766e`, Amber `#d97706`, Indigo `#4338ca`) | PHP `$featuredLocations` rendered as Field Survey Cards with GPS coordinates & CSRF favorite buttons | Editorial divider cut into twilight courtyard for Community reviews | `data-flow`<br>Energy: **6** | 6 location survey cards with clear pricing, rating stars, and Google Maps pin | Single-column survey cards with large tap targets for Favorite & Google Maps |
| **5 · `witnesses`**<br>INT. COURTYARD LANTERN — EVENING | **Witnesses (Community Reviews):** Những người vừa trở về kiểm chứng thực tế từng mùa hoa, quán cá hồi, homestay. | Eyebrow: `Review thực tế đã kiểm duyệt`<br>H2: `Trải nghiệm Cộng đồng`<br>+ Nút `Chia sẻ trải nghiệm` (`experience_form.php`) & `Xem tất cả bài viết` (`experiences.php`) + 3 bài `$latestExperiences` (tháng/năm đi, địa điểm, số sao, nội dung, lời khuyên, tác giả) | MCU editorial portraits & field notes, 85mm | Flow scene (`data-flow`) with staggered journal layout (1 featured wide dispatch + 2 vertical field notes) | Các bài viết hiển thị như trang sổ tay thực địa có đóng dấu `Trải nghiệm T2/2026` và khung `Lời khuyên` nổi bật | Warm evening courtyard `#13261e` with sunlit paper slips `#f7f2e7` | PHP `$latestExperiences` with author avatars, star ratings, and `tips_advice` callouts | Shutter-iris match cut onto the final Km0 compass | `data-flow`<br>Energy: **5** (The Quiet Scene) | Asymmetric 3-journal spread with real traveler advice highlighted | Stacked journal cards with clear author attribution |
| **6 · `departure`**<br>EXT. KM0 MILESTONE — GOLDEN HOUR | **Final Image & Resolution:** Mùa đẹp nhất đang chờ — khởi tạo chuyến đi riêng ngay hôm nay. | Eyebrow: `CỘT MỐC KM0 · BẮC DU TRAVEL PLANNER`<br>H2: `Mùa đẹp nhất không đợi ai. Lên lịch trình miền Bắc của riêng bạn ngay hôm nay.`<br>CTA 1: `Khám phá lịch trình Tháng <?= $selectedMonth ?>` (`templates.php?month=<?= $selectedMonth ?>`)<br>CTA 2: `Tự tạo chuyến đi mới` (`trips.php?action=new`) | Monumental center-axis WS, 35mm | Pinned finale (`--length: 150`, `data-hold`): compass rose locks onto North (`0°`) and golden-hour light blooms behind the Km0 milestone | Kim la bàn hội tụ về chính Bắc; người dùng nhấp chọn khám phá mẫu tháng hiện tại hoặc mở trình tạo chuyến đi mới | Deep forest `#091410` with golden-hour rim light `#f59e0b` | SVG Km0 Compass Monument + `slit-scan` closing title + dual primary CTAs | Flows directly into End Credits (`includes/footer.php`) | `--length: 140` (`data-hold`)<br>Energy: **7** | `data-still="0.7"` (Compass locked at 0° North framing the two final CTAs) | Portrait poster finale above `var(--safe-bottom)` |

- **Screens to the Offer:** **0 screens** (Bộ tìm kiếm lịch trình nhanh và 2 nút CTA chính *Khám phá lịch trình Tháng X* / *Tự tạo chuyến đi mới* nằm ngay ở khung hình đầu tiên `arrival`, đồng thời luôn truy cập được trên thanh điều hướng).

---

## 7. Continuity Sheet (Devices & Frequency)

| Scene | Type Performance (`kit/type-fx.js`) | Light Event | Camera / Motion (`kit/reel.js`) |
|---|---|---|---|
| **1 · `arrival`** | `blur-resolve` on main display statement (resolving like morning mist) | Warm vertical sun shaft opens across shutter aperture | Optical scale push `1 → 1.06` + Compass calibration |
| **2 · `almanac`** | `odometer` on active month number & template count; `select-box` on active season tag | Warm paper key light (`#f4efe4`) with amber seal highlight | Static lock-off (`data-flow`) — user's hand drives the 12-month dial |
| **3 · `chronicle`** | `read-fill` on the 4-step manifesto heading | Dappled shutter light bands passing behind the cards | Lateral dolly track (`.track` translating horizontally across the 4 steps) |
| **4 · `locations`** | Clean static typography for rapid price/coordinate scanning | High-key afternoon survey light | Static lock-off (`data-flow`) + interactive category filter lens |
| **5 · `witnesses`** | `highlighter` on key traveler tip phrase | Warm lantern rim light on dark jade ground | Static lock-off (`data-flow`) — breathing hold before finale |
| **6 · `departure`** | `slit-scan` on the final Km0 call-to-action title | Golden-hour radial bloom behind the Km0 Compass | Slow pull-out & compass lock to `0° North` (`data-hold`) |

---

## 8. Sub-Page Continuity & App Mode Production Plan (`references/app-mode.md`)

### A. Site Mode Continuity Across Sub-Pages (`templates.php`, `template_detail.php`, `locations.php`)
- **Shared Chromatic & Typographic World (`assets/css/cinematic-subpages.css`):**
  - Toàn bộ thanh điều hướng sơn mài tối (`#0b1914`), viền đồng mảnh (`--cin-brass`), font tiêu đề `Cormorant Garamond` + văn bản `Be Vietnam Pro` + số liệu `JetBrains Mono` được đồng bộ xuyên suốt từ `index.php` sang `templates.php`, `template_detail.php` và `locations.php`.
  - **Khung Khẩu Độ Đầu Trang (`.cin-subhero`):** Mở đầu mỗi trang con bằng dải cửa chớp gỗ Bắc Bộ, nhãn tọa độ `20°15'N — 23°22'N` và bảng điều phối lọc (`.cin-filter-console`).
  - **Thẻ Hồ Sơ Thực Địa (`.field-slip`):** Thay thế toàn bộ thẻ bo tròn đại trà bằng phiếu thực địa góc vuông `4px`, đóng dấu tọa độ GPS, khung lý do mùa vụ (`.field-reason-box`), hộp thông số đặc thù 3 nhóm (`.field-spec-box`) và sổ cái giá `tabular-nums` (`.price-mono`).

### B. App Mode Production — "Chuyến Đi Của Tôi" (`trips.php`, `trip_detail.php`) & "Quản Trị" (`admin.php`)
- **The Shift (Ca làm việc):**
  1. *Ca Lập Kế Hoạch Cá Nhân (`trips.php`, `trip_detail.php`):* Người trưởng nhóm điều phối lịch trình 2–5 ngày, cân đối khung giờ di chuyển, nơi lưu trú và ngân sách nhóm mà không bị tính trùng khoản chi.
  2. *Ca Trực Ban Biên Tập & Kiểm Duyệt (`admin.php`):* Quản trị viên duyệt hàng đợi bài trải nghiệm cộng đồng (`pending` → `approved` / `rejected` kèm lý do), kiểm soát độ phủ lịch trình mẫu 12 tháng và tỷ lệ chuyển đổi (`Lượt sao chép / Lượt xem`).
- **Signature Sets (10% Ngân sách Điện ảnh thay thế hàng thẻ KPI đại trà):**
  1. **`#expeditionLedgerSet` (`trip_detail.php`):** Kết hợp **Trục Ngày Hành Trình (`.route-strip-nodes`)** tương tác trực tiếp với bản đồ Leaflet và **Cầu Đối Soát Ngân Sách Chống Cộng Trùng (`.budget-bridge-meter`)**.
  2. **`#editorialMatrixSet` (`admin.php`):** **Ma Trận Độ Phủ 12 Tháng (`.editorial-matrix-grid`)** hiển thị số mẫu và lượt sao chép của từng tháng `T01`–`T12` cạnh bảng sổ cái vận hành.
- **The Lighting Plan & Status System:**
  - **One Lit Region (`.app-lit-callout`):** Sắc vàng hổ phách (`#d97706`) chỉ bật sáng ở vùng duy nhất cần con người hành động ngay (khi có bài chờ duyệt `pending_posts > 0`, khi dự toán vượt trần ngân sách, hoặc khi Checklist chưa hoàn tất). Khi hàng đợi đã sạch, giao diện chuyển sang trạng thái tĩnh lặng (`.app-calm-callout`).
  - **Status Marks (`.status-mark` — Hình học + Ký hiệu + Chữ):**
    - `●` `status-ok` (Xanh ngọc): Đã duyệt / Đã xuất bản / Đang diễn ra / Đang hiển thị
    - `▲` `status-warn` (Vàng hổ phách): Chờ duyệt / Bản nháp / Sắp khởi hành / Cần xử lý
    - `■` `status-fail` (Đỏ thẫm): Từ chối / Đang khóa
    - `○` `status-neutral` (Xám đá): Lưu trữ / Đã hoàn thành / Chế độ riêng tư
- **Keyboard Rig & Tabular Density (`assets/js/cinematic-app.js`):**
  - Mọi cột tiền tệ (VNĐ) và số liệu dùng `font-variant-numeric: tabular-nums` căn phải (`.num-col`).
  - Phím tắt ca trực: `1`–`6` đổi phân khu tức thì (`<120ms`), `j`/`k` di chuyển qua các dòng bảng hoặc hoạt động lịch trình, `Enter` mở thao tác chính của dòng đang chọn, `/` hoặc `Ctrl+K` mở **Bảng Lệnh Điều Phối Nhanh (`#cinAppPalette`)**.


