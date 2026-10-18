# Studio Choi 홈페이지

블랙 배경, 펜타그램 스타일의 정적 홈페이지입니다. 코드 수정 없이 `data/projects.json` 한 파일만 고치면 포트폴리오가 바뀝니다.

## 파일 구성

```
homepage/
├─ index.html            페이지 (디자인·동작)
├─ data/projects.json    스튜디오 정보 + 프로젝트 목록  ← 여기만 고치면 됩니다
└─ images/               프로젝트 이미지를 넣을 폴더 (직접 만들어 사용)
```

## 관리자 페이지 (추천)

미리보기 링크(https://claude.ai/artifact/PFVWQ7W39rPWnddVdTS7HU) 오른쪽 위 **관리** 메뉴, 또는 주소 끝에 `#admin`을 붙여 엽니다.

- 프로젝트 추가, 수정, 삭제, 순서 변경(↑↓), 숨기기/공개(●/◌)
- 대표 이미지와 상세 이미지 업로드 (끌어다 놓기, 여러 장 한 번에, 순서 변경)
- 스튜디오 이름, 소개, 연락처, 분야 수정
- 백업 탭에서 전체 내용을 projects.json으로 내려받기

저장하면 사이트에 바로 반영됩니다. 편집 권한이 있는 사람에게만 관리 메뉴가 보이고, 다른 방문자는 보기만 할 수 있습니다.

## 배포 사이트 관리자 (GitHub Pages)

https://921cds.github.io/921design/#admin 에서 같은 관리 화면을 씁니다. 저장하면 이 저장소의 `data/projects.json`과 `images/` 폴더에 바로 커밋되고, 1~2분 뒤 사이트에 반영됩니다.

처음 한 번만 GitHub 토큰을 만들어 로그인합니다.

1. https://github.com/settings/personal-access-tokens/new 에서 새 토큰을 만듭니다.
2. Repository access: **Only select repositories → 921design**
3. Permissions → Repository permissions → **Contents: Read and write**
4. 만든 토큰(`github_pat_…`)을 관리 화면에 붙여 넣고 로그인합니다.

토큰은 그 브라우저에만 저장되고, 로그아웃하면 지워집니다. 다른 컴퓨터에서는 같은 토큰을 다시 넣으면 됩니다. 큰 사진은 올릴 때 긴 변 2400px로 자동으로 줄어듭니다.

## 파일로 직접 관리 (직접 호스팅할 때)

아래는 관리자 페이지 없이 `data/projects.json`을 직접 고치는 방법입니다. 관리자 페이지에서 고친 내용은 백업 탭에서 이 파일로 받아 쓸 수 있습니다.

### 프로젝트 추가

`projects` 배열에 아래 블록을 하나 복사해 넣습니다. 목록에 넣은 순서대로 화면에 나옵니다.

```json
{
  "slug": "new-project",
  "title": "New Project",
  "client": "클라이언트 이름",
  "year": 2026,
  "disciplines": ["Identity", "Digital"],
  "size": "normal",
  "color": "#222222",
  "ink": "#FFFFFF",
  "mark": "NP",
  "cover": "images/new-project/cover.jpg",
  "summary": "목록에 보이는 한 줄 설명",
  "description": "상세 페이지에 보이는 본문",
  "images": ["images/new-project/01.jpg", "images/new-project/02.jpg"]
}
```

| 항목 | 설명 |
| --- | --- |
| `slug` | 영문 소문자와 하이픈으로 된 고유 주소. `사이트주소/#new-project`로 바로 열립니다. |
| `size` | `"large"`이면 전체 폭으로 크게, `"normal"`이면 2열 중 한 칸. |
| `cover` | 대표 이미지 경로. 비워 두면 `color`(배경색)·`ink`(글자색)·`mark`(큰 글자)로 타이포그래피 커버가 자동으로 그려집니다. |
| `images` | 상세 페이지 아래에 세로로 이어지는 이미지들. |
| `hidden` | `true`를 넣으면 지우지 않고 잠시 숨깁니다. |

- **수정**: 해당 블록의 값을 바꿉니다.
- **삭제**: 블록을 지웁니다 (앞뒤 쉼표 주의).
- **순서 변경**: 블록 위치를 옮깁니다.
- **분야 필터**: `site.disciplines`에 적힌 분야와 각 프로젝트의 `disciplines`가 자동으로 필터 버튼이 됩니다.

스튜디오 이름, 소개 문장, 연락처는 같은 파일의 `site` 항목에서 바꿉니다.

## 내 컴퓨터에서 보기

브라우저 보안 때문에 `index.html`을 더블클릭하면 프로젝트 목록이 안 뜹니다. 폴더에서 아래 명령을 실행한 뒤 http://localhost:8000 을 엽니다.

```
python3 -m http.server 8000
```

GitHub Pages, Netlify, Vercel 같은 정적 호스팅에 폴더째 올리면 그대로 동작합니다.

## 파일을 더블클릭해서 열 때
브라우저는 컴퓨터에서 바로 연 페이지가 data/projects.json을 읽지 못하게 막습니다. 그래서 index.html 안의 `<script id="site-data">`에 같은 내용을 한 벌 넣어 두었습니다. projects.json을 고치면 이 부분도 같이 바꿔 주세요.
