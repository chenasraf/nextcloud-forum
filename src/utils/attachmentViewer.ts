interface ViewerFileInfo {
  fileid: number
  filename: string
  basename: string
  mime: string
  source: string
}

interface Viewer {
  mimetypes: string[]
  open(options: { fileInfo: ViewerFileInfo; list: ViewerFileInfo[]; enableSidebar: boolean }): void
}

function getViewer(): Viewer | undefined {
  return (window as unknown as { OCA?: { Viewer?: Viewer } }).OCA?.Viewer
}

/**
 * Opens attachment links in the Viewer. Links keep their default behaviour when the Viewer cannot
 * display the file, and when a modifier key asks the browser to open them elsewhere.
 *
 * Post content is rendered by the backend and shown in several views, so this is meant to listen
 * on the document rather than on a single component.
 */
export function handleAttachmentClick(event: MouseEvent): void {
  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
    return
  }

  const link = (event.target as Element | null)?.closest?.<HTMLAnchorElement>(
    'a.attachment-name[data-viewer-mime]',
  )
  if (!link) {
    return
  }

  const mime = link.dataset.viewerMime!
  const viewer = getViewer()
  if (!viewer?.mimetypes.includes(mime)) {
    return
  }

  event.preventDefault()

  const name = link.textContent ?? ''
  // The source is the forum's endpoint: readers usually have no access to the author's file
  const fileInfo: ViewerFileInfo = {
    fileid: Number(link.dataset.fileId),
    filename: `/${name}`,
    basename: name,
    mime,
    source: link.href,
  }
  viewer.open({ fileInfo, list: [fileInfo], enableSidebar: false })
}
