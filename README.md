# JohnCMS 7 mod version
# Giới thiệu tổng quan (Introduction)
Đây là mã nguồn CMS dành cho mobile được viết bằng PHP. Có đầy đủ chức của một CMS như: hệ thống quản lý người dùng, diễn đàn thảo luận, lưu bút trò chuyện, thư viên lưu trữ tài liệu + sách, mô đun tải về, mô đun album ảnh,...

Đây là một mã nguồn gọn nhẹ đáp ứng được nhu cầu tạo một trang CMS đơn giản, load nhanh, hiệu quả, thích hợp cho người mới bắt đầu làm quen với việc tạo lập một trang CMS bằng PHP + MySQL.
# Lý do chọn mã nguồn này để chỉnh sửa (Reason for modify JohnCMS)
Từ việc JohnCMS thay đổi cấu trúc mã nguồn từ phiên bản 7.x.x sử dụng cấu trúc mới DI container, PDO class, Gettext translator... hiện đại hơn, nhanh hơn. Mình quyết định mod lại mã nguồn này và thêm vào đó những module cần thiết.
# Danh mục các thay đổi mà tác giả đã đóng góp vào phiên bản này (change log)
- Áp dụng theme selemet thay vì theme default như trước đây sau khi cài đặt.
- Thêm màu nick cho các thành viên ban quản trị *(add color nick for adminstrators)*. Tức mỗi thành viên tùy theo chức vụ trong forum sẽ màu nick khác nhau để dễ phân biệt giữa thành viên bình thường và thành viên ban quản trị.
- Thêm biểu tượng cấp bậc từ gà con đến rìu vàng chiến chấm thể hiện cho số bài viết được trên forum của người dùng *(add experience icons for users)*
- Đưa bài viết mới ra trang chủ. *(show new posts forum on main page)*
- Thêm module thanks forum *(add module thanks forum)*. Là module cho phép bạn gửi lời cảm ơn đến các bài viết có ích.
- Hiện thị thời gian hệ thống ở cuối trang *(Show system time at end of page)*
- Trả lời nhanh trong diễn đàn luôn mở *(Quick post always enable)*
- Đóng góp các bản dịch tiếng Việt hoàn chỉnh *(Vietnamese translation edited)*
- Viết lại đường dẫn các bài viết trên diễn đàn để thân thiện hơn với các công cụ tìm kiếm. Nếu có lỗi xảy ra, bạn có thể bật/tắt module trong **Admin Panel -> System settings** *(Add module rewrite url SEO friendly on forum. If module have some mistake, You could turn ON/OFF at **Admin Panel -> System settings**)*. Ví dụ: thay vì đường dẫn là HOME/forum/index.php?id=1 thì đường dẫn sẽ thay bằng HOME/forum/ten-bai-viet-1.html
- Đưa TOP người dùng ra trang chủ *(show TOP users on main page)*
- Sửa một lỗi bảo mật liên quan đến XSS trong chức năng đổi tên chủ đề *(Fix bug XSS in Rename topic)* . File: forum/includes/ren.php
- Thêm các nút và link để share lên các trang mạng xã hội *(Add button share on social networks)*
- Đưa ra gợi ý các bài viết cùng chuyên mục (hoặc liên quan) với bài viết hiện hành *(add suggest other topics)*
- Thay đổi nhỏ trong thuật toán cắt chuỗi. VD: Chuỗi ban đầu **"JohnCMS 7 edition version"** cắt 12 kí tự từ vị trí đầu tiên (vị trí 0) thì được kết quả là: **"JohnCMS 7 edition"** (thay vì "JohnCMS 7 ed")
- Thêm các thẻ meta description ở đầu trang cho các bài viết, lấy từ 120 kí trong nội dung bài viết *(add meta tags description on head page for articles, content get from 120 chars of text).* Mục đích thêm là để tăng khả năng SEO của trang.
- Thêm thuộc tính word-wrap vào các file css để khắc phục lỗi vỡ khung khi người dùng cố tình gửi một nội dung như "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" có thể dẫn đến đoạn text bị vỡ ra ngoài khung. Lưu ý thuộc tính word-wap chỉ hoạt động trên các trình duyệt hỗ trợ CSS3 và một số trình duyệt
- Thay đổi thuật toán xác thực người dùng, ở phiên bản cũ, cookie lưu ID và Password người dùng để xác thực. Việc lưu cookie như vậy rất nguy hiểm vì ID và Password là giá trị thường cố định nên khi bị đánh cắp cookie thì xem như tài khoản đã bị đánh cắp. Ở phiên bản mới, khi người dùng đăng nhập (nhập username và password) thành công thì một session id (ssid) mới sẽ tạo từ một chuỗi ngẫu nhiên. Và ssid này sẽ dùng để xác thực thay cho password. ssid này chỉ có giá trị trong một phiên đăng nhập, nếu người dùng đăng xuất và đăng nhập lại (hoặc đăng nhập trên thiết bị khác) thì ssid sẽ được tạo mới, và ssid cũ không còn hiệu lực. Nếu tấn công brute force cookie sẽ bị chặn IP... *(Change algorithm authorize. In old version, ID and Password was saved to cookie. It''s bad idea. Because password is not modified regularly. With new algorithm, when users login success, a SSID will be generate by random string. It will be suplanted password. SSID live only in a login session. If user logout and login again, SSID would create new and old SSID outdate. If brute force cookie attack, would be ban IP)*
- Mã hoá mật khẩu bằng **bcrypt** thay vì md5 như trước đây.
- Thêm bộ smilies popo.
- Thêm module forum notification. Mỗi khi có bình luận hay lời cảm ơn vào một topic mà mình quan tâm (hoặc tác giả topic) sẽ nhận được thông báo.
- Fix một lỗi nhỏ trong phần hiển thị online của khách và thêm module kỷ lục online (module cho phép xem thời điểm và số người onl cao nhất trong một thời điểm).
- Thêm thẻ tag thành viên trong bài viết. Bạn có thể thêm kí tự @ trước username là có thể đánh dấu họ, khi bạn đánh dấu như vậy thì hệ thống sẽ gửi một tin nhắn hệ thống đến người được đánh dấu rằng bạn đã nhắc họ. Chỉ có thể đánh dấu tối đa 10 người trong một bài viết. *(add module mentions users)*
- Thêm bbcode img dạng: \[img=url]
- Fix news bug. Trong phần tin tức có một bug nhỏ liên quan đến hiển thị nội dung tin tức.
# Yêu cầu hệ thống (System Requirements)
- Phiên bản PHP cao hơn **5.6** *(PHP 5.6 higher version)*
- Hỗ trợ các class và thư viện sau: *(support classes and libraries following)*
  + PDO class
  + GD library
  + Zlib library
  + mbstring library
# Cài đặt và sử dụng (Install)
Tiến hành clone source về và upload vào thư mục mà bạn muốn cài đặt
Sau đó chạy đường dẫn sau để tiến hành cài đặt: HOME/install/index.php
Chọn ngôn ngữ tiếng Việt nếu như bạn không dùng được tiếng Anh. (Tôi đã đóng góp bản dịch tiếng Việt vào cài đặt).
