#!/bin/bash
PHP=${PHP:-php}
PROJ=$(cd "$(dirname "$0")/.." && pwd)
BASE=http://127.0.0.1:8099
DBP=$PROJ/storage/classconnect.sqlite
PASS=0; FAIL=0
pass(){ PASS=$((PASS+1)); echo "  ok    $1"; }
fail(){ FAIL=$((FAIL+1)); echo "  FAIL  $1  -> $2"; }
assert_eq(){ if [ "$2" = "$3" ]; then pass "$1"; else fail "$1" "got '$2', wanted '$3'"; fi; }
assert_has(){ if printf '%s' "$2" | grep -qF -- "$3"; then pass "$1"; else fail "$1" "missing '$3'"; fi; }
assert_lacks(){ if printf '%s' "$2" | grep -qF -- "$3"; then fail "$1" "unexpected '$3'"; else pass "$1"; fi; }
status(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
token_for(){ curl -s -c "$1" -b "$1" "$BASE/$2" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
dbq(){ SQL="$1" DBP="$DBP" $PHP -r 'echo (new PDO("sqlite:" . getenv("DBP")))->query(getenv("SQL"))->fetchColumn();'; }
register(){ # jar name email role
  local tok; tok=$(token_for "$1" register.php)
  curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$1" -b "$1" -X POST "$BASE/register.php" \
    --data-urlencode "csrf_token=$tok" --data-urlencode "full_name=$2" --data-urlencode "email=$3" \
    --data-urlencode "role=$4" --data-urlencode "password=Sample123" --data-urlencode "confirm_password=Sample123"; }

cd $PROJ && rm -f storage/classconnect.sqlite && rm -f storage/uploads/* && rm -f ${TMPDIR:-/tmp}/php-server.log
mkdir -p /tmp/t && rm -f /tmp/t/*.jar
printf '%s\n' '%PDF-1.4 cell notes body' > /tmp/t/cell.pdf
printf '%s\n' 'this is plain text, not a pdf' > /tmp/t/fake.pdf
printf 'MZ fake program' > /tmp/t/setup.exe
{ printf '%s\n' '%PDF-1.4'; head -c 26500000 /dev/zero; } > /tmp/t/big25.pdf
{ printf '%s\n' '%PDF-1.4'; head -c 28000000 /dev/zero; } > /tmp/t/huge.pdf
printf '%s\n' '%PDF-1.7 replacement body' > /tmp/t/v2.pdf
$PHP -d display_errors=0 -d log_errors=1 -S 127.0.0.1:8099 -t public > ${TMPDIR:-/tmp}/php-server.log 2>&1 &
SERVER=$!
sleep 1.5
T=/tmp/t/teacher.jar; S=/tmp/t/student.jar; O=/tmp/t/outsider.jar

echo "== Public pages and security"
assert_eq "landing page loads" "$(status $BASE/)" 200
assert_eq "stylesheet is served" "$(status $BASE/assets/css/style.css)" 200
assert_eq "script is served" "$(status $BASE/assets/js/app.js)" 200
assert_has "content security policy header set" "$(curl -sI $BASE/login.php)" "Content-Security-Policy"
assert_eq "login without CSRF token is refused" "$(status -X POST $BASE/login.php -d email=x@example.com -d password=zzzzzz)" 400
assert_eq "logged-out dashboard sends you to log in" "$(curl -s -o /dev/null -w '%{redirect_url}' $BASE/dashboard.php)" "$BASE/login.php"

echo "== Registration"
r=$(register "$T" "Maria Santos" maria@example.com teacher)
assert_has "teacher registers and lands on dashboard" "$r" "dashboard.php"
tok=$(token_for "$S" register.php)
r=$(curl -s -c "$S" -b "$S" -X POST $BASE/register.php --data-urlencode "csrf_token=$tok" \
  --data-urlencode "full_name=A" --data-urlencode "email=bad" --data-urlencode "role=" \
  --data-urlencode "password=short" --data-urlencode "confirm_password=other")
assert_has "bad sign-up shows field errors" "$r" "Enter a valid email address."
assert_has "weak password message shown" "$r" "Use at least 8 characters."
assert_has "missing role message shown" "$r" "Choose Teacher or Student."
r=$(register "$S" "Noah Tan" noah@example.com student)
assert_has "student registers and lands on dashboard" "$r" "dashboard.php"
r=$(register "$O" "Omar Ali" omar@example.com student)
assert_has "second student registers" "$r" "dashboard.php"

echo "== Teacher creates a classroom and uploads a file"
tok=$(token_for "$T" classroom-form.php)
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$T" -b "$T" -X POST "$BASE/classroom-form.php" \
  --data-urlencode "csrf_token=$tok" --data-urlencode "name=Biology 101" \
  --data-urlencode "subject=Science" --data-urlencode "description=Cells and genetics")
assert_has "classroom created and opened" "$r" "classroom.php?id=1"
CODE=$(dbq "SELECT class_code FROM classrooms WHERE id = 1")
assert_eq "class code is six characters" "${#CODE}" 6

tok=$(token_for "$T" upload.php)
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$T" -b "$T" -X POST "$BASE/upload.php" \
  -F "csrf_token=$tok" -F "classroom_id=1" -F "title=Cell notes" -F "description=Week 1 reading" \
  -F "file=@/tmp/t/cell.pdf;filename=cell notes.pdf;type=application/pdf")
assert_has "upload accepted and redirected" "$r" "upload.php?classroom=1"
assert_has "upload success message shown" "$(curl -s -b "$T" $BASE/upload.php)" "was uploaded"
assert_eq "stored file exists on disk" "$(ls $PROJ/storage/uploads | wc -l)" 1

echo "== Student joins and downloads"
tok=$(token_for "$S" join.php)
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$S" -b "$S" -X POST "$BASE/join.php" \
  --data-urlencode "csrf_token=$tok" --data-urlencode "class_code= ${CODE,,} ")
assert_has "student joins with lowercase code and spaces" "$r" "classroom.php?id=1"
assert_has "classroom page lists the material" "$(curl -s -b "$S" "$BASE/classroom.php?id=1")" "Cell notes"
curl -s -b "$S" -D /tmp/t/h.txt -o /tmp/t/dl.pdf "$BASE/download.php?id=1"
if cmp -s /tmp/t/dl.pdf /tmp/t/cell.pdf; then pass "download returns the exact file"; else fail "download returns the exact file" "bytes differ"; fi
assert_has "download is an attachment with original name" "$(cat /tmp/t/h.txt)" 'attachment; filename="cell notes.pdf"'
assert_has "view mode serves PDF inline" "$(curl -s -b "$S" -D - -o /dev/null "$BASE/download.php?id=1&inline=1")" "inline;"
assert_has "security headers on downloads" "$(cat /tmp/t/h.txt)" "X-Content-Type-Options: nosniff"

echo "== Access rules"
assert_eq "outsider cannot open the classroom" "$(status -b "$O" "$BASE/classroom.php?id=1")" 404
assert_eq "outsider cannot download the file" "$(status -b "$O" "$BASE/download.php?id=1")" 404
assert_eq "student cannot open the upload page" "$(status -b "$S" "$BASE/upload.php")" 403
assert_eq "teacher cannot open the join page" "$(status -b "$T" "$BASE/join.php")" 403
assert_eq "missing classroom shows 404" "$(status -b "$T" "$BASE/classroom.php?id=99999")" 404
assert_has "404 page has friendly text" "$(curl -s -b "$T" "$BASE/classroom.php?id=99999")" "couldn"
assert_has "teacher sees the student roster" "$(curl -s -b "$T" "$BASE/classroom.php?id=1")" "Noah Tan"

echo "== Upload validation"
tok=$(token_for "$T" upload.php)
r=$(curl -s -b "$T" -c "$T" -X POST "$BASE/upload.php" -F "csrf_token=$tok" -F classroom_id=1 -F title="Fake" \
  -F "file=@/tmp/t/fake.pdf;filename=fake.pdf")
assert_has "plain text renamed to .pdf is rejected" "$r" "match a .pdf file"
r=$(curl -s -b "$T" -c "$T" -X POST "$BASE/upload.php" -F "csrf_token=$tok" -F classroom_id=1 -F title="Program" \
  -F "file=@/tmp/t/setup.exe;filename=setup.exe")
assert_has "executable is rejected by type" "$r" "not supported"
r=$(curl -s -b "$T" -c "$T" -X POST "$BASE/upload.php" -F "csrf_token=$tok" -F classroom_id=1 -F title="Big" \
  -F "file=@/tmp/t/big25.pdf;filename=big.pdf")
assert_has "file over the upload limit is rejected" "$r" "larger than the 25 MB upload limit"
r=$(curl -s -b "$T" -c "$T" -X POST "$BASE/upload.php" -F "csrf_token=$tok" -F classroom_id=1 -F title="Huge" \
  -F "file=@/tmp/t/huge.pdf;filename=huge.pdf")
assert_eq "request above the server post limit is redirected" "$(status -b "$T" -c "$T" -X POST "$BASE/upload.php" -F "csrf_token=$tok" -F classroom_id=1 -F title="Huge" -F "file=@/tmp/t/huge.pdf;filename=huge.pdf")" 302
assert_has "post-limit message shown on the next page" "$(curl -s -b "$T" $BASE/upload.php)" "larger than the server allows"
assert_eq "rejected files were not kept" "$(ls $PROJ/storage/uploads | wc -l)" 1

echo "== Editing a material, including replacing its file"
tok=$(token_for "$T" "material-edit.php?id=1")
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$T" -b "$T" -X POST "$BASE/material-edit.php?id=1" \
  -F "csrf_token=$tok" -F "title=Cell notes v2" -F "description=Updated" -F "file=@/tmp/t/v2.pdf;filename=cell-v2.pdf")
assert_has "edit saves and returns to the list" "$r" "upload.php?classroom=1"
curl -s -b "$S" "$BASE/download.php?id=1" -o /tmp/t/dl2.pdf
if cmp -s /tmp/t/dl2.pdf /tmp/t/v2.pdf; then pass "student now downloads the replaced file"; else fail "replaced file" "old content served"; fi
assert_eq "old file removed from disk" "$(ls $PROJ/storage/uploads | wc -l)" 1

echo "== Teacher removes a student; student rejoins and leaves"
NOAH=$(dbq "SELECT id FROM users WHERE email = 'noah@example.com'")
tok=$(token_for "$T" "classroom.php?id=1")
curl -s -o /dev/null -c "$T" -b "$T" -X POST "$BASE/action.php" --data-urlencode "csrf_token=$tok" \
  -d action=remove_student -d classroom_id=1 -d student_id=$NOAH
assert_eq "removed student loses access" "$(status -b "$S" "$BASE/classroom.php?id=1")" 404
assert_eq "removed student loses downloads" "$(status -b "$S" "$BASE/download.php?id=1")" 404
tok=$(token_for "$S" join.php)
curl -s -o /dev/null -c "$S" -b "$S" -X POST "$BASE/join.php" --data-urlencode "csrf_token=$tok" --data-urlencode "class_code=$CODE"
tok=$(token_for "$S" "classroom.php?id=1")
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$S" -b "$S" -X POST "$BASE/action.php" \
  --data-urlencode "csrf_token=$tok" -d action=leave -d classroom_id=1)
assert_has "student leaves the classroom" "$r" "dashboard.php"
assert_eq "left student loses access" "$(status -b "$S" "$BASE/classroom.php?id=1")" 404

echo "== Class code regeneration"
tok=$(token_for "$T" "classroom-form.php?id=1")
curl -s -o /dev/null -c "$T" -b "$T" -X POST "$BASE/action.php" --data-urlencode "csrf_token=$tok" -d action=regenerate_code -d classroom_id=1
NEW=$(dbq "SELECT class_code FROM classrooms WHERE id = 1")
if [ "$NEW" != "$CODE" ]; then pass "class code changed"; else fail "class code changed" "same code"; fi
tok=$(token_for "$O" join.php)
r=$(curl -s -c "$O" -b "$O" -X POST "$BASE/join.php" --data-urlencode "csrf_token=$tok" --data-urlencode "class_code=$CODE")
assert_has "old code is refused" "$r" "No classroom matches that code"
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$O" -b "$O" -X POST "$BASE/join.php" --data-urlencode "csrf_token=$tok" --data-urlencode "class_code=$NEW")
assert_has "new code works for the outsider" "$r" "classroom.php?id=1"

echo "== Deleting a material and a classroom"
tok=$(token_for "$T" upload.php)
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$T" -b "$T" -X POST "$BASE/action.php" \
  --data-urlencode "csrf_token=$tok" -d action=delete_material -d material_id=1 -d return_to=upload)
assert_has "material delete returns to the list" "$r" "upload.php?classroom=1"
assert_eq "material file removed from disk" "$(ls $PROJ/storage/uploads | wc -l)" 0
tok=$(token_for "$T" upload.php)
curl -s -o /dev/null -c "$T" -b "$T" -X POST "$BASE/upload.php" -F "csrf_token=$tok" -F classroom_id=1 -F title="Lab sheet" -F "file=@/tmp/t/cell.pdf;filename=lab.pdf"
tok=$(token_for "$T" "classroom-form.php?id=1")
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$T" -b "$T" -X POST "$BASE/action.php" \
  --data-urlencode "csrf_token=$tok" -d action=delete_classroom -d classroom_id=1)
assert_has "classroom delete returns to dashboard" "$r" "dashboard.php"
assert_eq "deleted classroom is gone" "$(status -b "$T" "$BASE/classroom.php?id=1")" 404
assert_eq "its stored files were removed" "$(ls $PROJ/storage/uploads | wc -l)" 0
assert_eq "its materials were removed" "$(dbq "SELECT COUNT(*) FROM learning_materials")" 0

echo "== Session, logout and login throttling"
tok=$(token_for "$T" dashboard.php)
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$T" -b "$T" -X POST "$BASE/logout.php" --data-urlencode "csrf_token=$tok")
assert_has "logout returns to home" "$r" "index.php"
assert_eq "after logout the dashboard asks for login" "$(curl -s -o /dev/null -w '%{redirect_url}' -b "$T" $BASE/dashboard.php)" "$BASE/login.php"
V=/tmp/t/visitor.jar; rm -f $V
for i in 1 2 3 4 5 6 7 8; do
  tok=$(token_for "$V" login.php)
  curl -s -o /dev/null -c "$V" -b "$V" -X POST "$BASE/login.php" --data-urlencode "csrf_token=$tok" \
    --data-urlencode "email=omar@example.com" --data-urlencode "password=wrongpass1"
done
tok=$(token_for "$V" login.php)
r=$(curl -s -c "$V" -b "$V" -X POST "$BASE/login.php" --data-urlencode "csrf_token=$tok" \
  --data-urlencode "email=omar@example.com" --data-urlencode "password=Sample123")
assert_has "login is throttled after repeated failures" "$r" "Too many failed attempts"
tok=$(token_for "$T" login.php)
r=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$T" -b "$T" -X POST "$BASE/login.php" \
  --data-urlencode "csrf_token=$tok" --data-urlencode "email=maria@example.com" --data-urlencode "password=Sample123")
assert_has "correct password logs teacher back in" "$r" "dashboard.php"

echo "== Server log"
if grep -E "PHP (Warning|Fatal|Notice|Deprecated)" ${TMPDIR:-/tmp}/php-server.log | grep -vq "POST Content-Length"; then
  fail "no PHP warnings or errors during the run" "$(grep -E 'PHP (Warning|Fatal|Notice|Deprecated)' ${TMPDIR:-/tmp}/php-server.log | head -3)"
else
  pass "no PHP warnings or errors during the run"
fi

kill $SERVER 2>/dev/null; sleep 0.5
echo
echo "RESULT: $PASS passed, $FAIL failed"
