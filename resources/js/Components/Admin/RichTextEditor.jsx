import React, { useRef, useEffect, useState, useCallback } from 'react';

export default function RichTextEditor({ value = '', onChange, placeholder = 'Write description here...', minHeight = '200px' }) {
  const editorRef = useRef(null);
  const [isHtmlMode, setIsHtmlMode] = useState(false);
  const [htmlValue, setHtmlValue] = useState(value || '');
  const [activeFormats, setActiveFormats] = useState({
    bold: false,
    italic: false,
    underline: false,
    justifyLeft: false,
    justifyCenter: false,
    justifyRight: false,
    justifyFull: false,
    insertUnorderedList: false,
    insertOrderedList: false,
  });

  // Sync external value to editor DOM when value changes externally (and not actively focused)
  useEffect(() => {
    setHtmlValue(value || '');
    if (editorRef.current && !isHtmlMode) {
      if (editorRef.current.innerHTML !== (value || '')) {
        editorRef.current.innerHTML = value || '';
      }
    }
  }, [value, isHtmlMode]);

  const updateActiveStates = useCallback(() => {
    if (isHtmlMode || typeof document === 'undefined') return;
    try {
      setActiveFormats({
        bold: document.queryCommandState('bold'),
        italic: document.queryCommandState('italic'),
        underline: document.queryCommandState('underline'),
        justifyLeft: document.queryCommandState('justifyLeft'),
        justifyCenter: document.queryCommandState('justifyCenter'),
        justifyRight: document.queryCommandState('justifyRight'),
        justifyFull: document.queryCommandState('justifyFull'),
        insertUnorderedList: document.queryCommandState('insertUnorderedList'),
        insertOrderedList: document.queryCommandState('insertOrderedList'),
      });
    } catch {
      // ignore
    }
  }, [isHtmlMode]);

  const handleInput = () => {
    if (editorRef.current) {
      const newHtml = editorRef.current.innerHTML;
      setHtmlValue(newHtml);
      if (onChange) {
        onChange(newHtml);
      }
      updateActiveStates();
    }
  };

  const handleHtmlTextareaChange = (e) => {
    const newHtml = e.target.value;
    setHtmlValue(newHtml);
    if (onChange) {
      onChange(newHtml);
    }
  };

  const exec = (command, value = null) => {
    if (isHtmlMode) return;
    if (editorRef.current) {
      editorRef.current.focus();
    }
    document.execCommand(command, false, value);
    handleInput();
  };

  const applyFormatBlock = (tag) => {
    if (isHtmlMode) return;
    exec('formatBlock', tag ? `<${tag}>` : '<p>');
  };

  const handleAddLink = () => {
    if (isHtmlMode) return;
    const url = prompt('Enter URL (e.g. https://example.com):');
    if (url) {
      exec('createLink', url);
    }
  };

  const toggleHtmlMode = () => {
    if (isHtmlMode) {
      // Switching from HTML to visual
      setIsHtmlMode(false);
      setTimeout(() => {
        if (editorRef.current) {
          editorRef.current.innerHTML = htmlValue;
          editorRef.current.focus();
        }
      }, 0);
    } else {
      // Switching from visual to HTML
      if (editorRef.current) {
        setHtmlValue(editorRef.current.innerHTML);
      }
      setIsHtmlMode(true);
    }
  };

  return (
    <div className="w-full border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm focus-within:ring-2 focus-within:ring-orange-400/40 focus-within:border-orange-500 transition-all">
      {/* ── Toolbar ── */}
      <div className="flex flex-wrap items-center gap-1 p-2 bg-gray-50 border-b border-gray-200 text-gray-700 select-none">
        
        {/* Alignment group */}
        <div className="flex items-center bg-white border border-gray-200 rounded-lg p-0.5 shadow-2xs">
          <button
            type="button"
            title="Align Left"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('justifyLeft'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 transition-colors disabled:opacity-40 ${activeFormats.justifyLeft ? 'bg-orange-50 text-orange-600 font-bold' : 'text-gray-600'}`}
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h10M4 18h14" />
            </svg>
          </button>

          <button
            type="button"
            title="Align Center"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('justifyCenter'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 transition-colors disabled:opacity-40 ${activeFormats.justifyCenter ? 'bg-orange-50 text-orange-600 font-bold' : 'text-gray-600'}`}
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M7 12h10M5 18h14" />
            </svg>
          </button>

          <button
            type="button"
            title="Align Right"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('justifyRight'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 transition-colors disabled:opacity-40 ${activeFormats.justifyRight ? 'bg-orange-50 text-orange-600 font-bold' : 'text-gray-600'}`}
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M10 12h10M6 18h14" />
            </svg>
          </button>

          <button
            type="button"
            title="Justify Full"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('justifyFull'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 transition-colors disabled:opacity-40 ${activeFormats.justifyFull ? 'bg-orange-50 text-orange-600 font-bold' : 'text-gray-600'}`}
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>

        <div className="h-5 w-px bg-gray-300 mx-1" />

        {/* Text Styles */}
        <div className="flex items-center bg-white border border-gray-200 rounded-lg p-0.5 shadow-2xs">
          <button
            type="button"
            title="Bold (Ctrl+B)"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('bold'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 font-bold transition-colors disabled:opacity-40 ${activeFormats.bold ? 'bg-orange-50 text-orange-600 font-extrabold' : 'text-gray-700'}`}
          >
            <strong>B</strong>
          </button>

          <button
            type="button"
            title="Italic (Ctrl+I)"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('italic'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 italic transition-colors disabled:opacity-40 ${activeFormats.italic ? 'bg-orange-50 text-orange-600' : 'text-gray-700'}`}
          >
            <em>I</em>
          </button>

          <button
            type="button"
            title="Underline (Ctrl+U)"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('underline'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 underline transition-colors disabled:opacity-40 ${activeFormats.underline ? 'bg-orange-50 text-orange-600' : 'text-gray-700'}`}
          >
            <u>U</u>
          </button>

          <button
            type="button"
            title="Strikethrough"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('strikeThrough'); }}
            className="p-1.5 rounded-md hover:bg-gray-100 text-gray-700 line-through transition-colors disabled:opacity-40"
          >
            <s>S</s>
          </button>
        </div>

        <div className="h-5 w-px bg-gray-300 mx-1" />

        {/* Headings / Block Dropdown */}
        <select
          title="Format Block"
          disabled={isHtmlMode}
          onChange={(e) => applyFormatBlock(e.target.value)}
          defaultValue="p"
          className="h-8 bg-white border border-gray-200 text-gray-700 text-xs rounded-lg px-2 focus:outline-none focus:ring-1 focus:ring-orange-400 disabled:opacity-40"
        >
          <option value="p">Normal Text (Paragraph)</option>
          <option value="h2">Heading 2 (Large)</option>
          <option value="h3">Heading 3 (Medium)</option>
          <option value="h4">Heading 4 (Small)</option>
          <option value="blockquote">Quote Block</option>
        </select>

        <div className="h-5 w-px bg-gray-300 mx-1" />

        {/* Lists */}
        <div className="flex items-center bg-white border border-gray-200 rounded-lg p-0.5 shadow-2xs">
          <button
            type="button"
            title="Bullet List"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('insertUnorderedList'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 transition-colors disabled:opacity-40 ${activeFormats.insertUnorderedList ? 'bg-orange-50 text-orange-600' : 'text-gray-600'}`}
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h.01M8 6h12M4 12h.01M8 12h12M4 18h.01M8 18h12" />
            </svg>
          </button>

          <button
            type="button"
            title="Numbered List"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('insertOrderedList'); }}
            className={`p-1.5 rounded-md hover:bg-gray-100 transition-colors disabled:opacity-40 ${activeFormats.insertOrderedList ? 'bg-orange-50 text-orange-600' : 'text-gray-600'}`}
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M7 6h13M7 12h13M7 18h13M3 6h.01M3 12h.01M3 18h.01" />
            </svg>
          </button>
        </div>

        {/* Inserts & Utilities */}
        <div className="flex items-center bg-white border border-gray-200 rounded-lg p-0.5 shadow-2xs ml-auto sm:ml-0">
          <button
            type="button"
            title="Insert Link"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); handleAddLink(); }}
            className="p-1.5 rounded-md hover:bg-gray-100 text-gray-600 transition-colors disabled:opacity-40"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
          </button>

          <button
            type="button"
            title="Horizontal Divider"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('insertHorizontalRule'); }}
            className="p-1.5 rounded-md hover:bg-gray-100 text-gray-600 transition-colors disabled:opacity-40"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 12h16" />
            </svg>
          </button>

          <button
            type="button"
            title="Clear Formatting"
            disabled={isHtmlMode}
            onClick={(e) => { e.preventDefault(); exec('removeFormat'); }}
            className="p-1.5 rounded-md hover:bg-gray-100 text-red-500 transition-colors disabled:opacity-40"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>

        {/* HTML Source Toggle */}
        <button
          type="button"
          onClick={toggleHtmlMode}
          title={isHtmlMode ? 'Switch to Visual Editor' : 'Edit HTML Source Code'}
          className={`ml-auto px-2.5 py-1 text-xs font-semibold rounded-lg border transition-all flex items-center gap-1.5 ${
            isHtmlMode
              ? 'bg-orange-600 text-white border-orange-600 shadow-xs'
              : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100'
          }`}
        >
          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
            <path strokeLinecap="round" strokeLinejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
          <span>{isHtmlMode ? 'Visual Editor' : 'HTML Code'}</span>
        </button>
      </div>

      {/* ── Editor Canvas ── */}
      {isHtmlMode ? (
        <textarea
          value={htmlValue}
          onChange={handleHtmlTextareaChange}
          placeholder="Edit raw HTML..."
          rows={10}
          style={{ minHeight }}
          className="w-full p-4 font-mono text-xs bg-gray-900 text-gray-100 focus:outline-none resize-y"
          spellCheck={false}
        />
      ) : (
        <div
          ref={editorRef}
          contentEditable
          onInput={handleInput}
          onKeyUp={updateActiveStates}
          onMouseUp={updateActiveStates}
          data-placeholder={placeholder}
          style={{ minHeight }}
          className="p-4 max-h-[500px] overflow-y-auto text-sm text-gray-800 focus:outline-none leading-relaxed prose prose-sm max-w-none empty:before:content-[attr(data-placeholder)] empty:before:text-gray-400 empty:before:pointer-events-none [&_p]:mb-2 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:my-3 [&_h3]:text-lg [&_h3]:font-bold [&_h3]:my-2 [&_h4]:font-semibold [&_h4]:my-1.5 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-2 [&_blockquote]:border-l-4 [&_blockquote]:border-orange-400 [&_blockquote]:pl-3 [&_blockquote]:italic [&_blockquote]:text-gray-600 [&_a]:text-orange-600 [&_a]:underline"
        />
      )}
    </div>
  );
}
