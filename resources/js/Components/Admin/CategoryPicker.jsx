/** Selecting an ancestor deliberately assigns the product to that level. */
export default function CategoryPicker({ categories = [], value, onChange, className = '' }) {
  const selected = categories.find(category => String(category.id) === String(value));
  const path = selected?.path_ids || (selected ? [selected.id] : []);
  const roots = categories.filter(category => !category.parent_id && category.hierarchy_valid !== false);
  const childrenOf = id => categories.filter(category => String(category.parent_id) === String(id) && category.hierarchy_valid !== false);
  const levels = [roots, path[0] ? childrenOf(path[0]) : [], path[1] ? childrenOf(path[1]) : []];
  return (
    <div className="space-y-3">
      {['Category', 'Subcategory', 'Sub-subcategory'].map((label, index) => (
        <label key={label} className="block text-xs font-medium text-gray-600">
          <span className="mb-1 block">{label}{index > 0 ? ' (optional)' : ''}</span>
          <select aria-label={label} value={path[index] || ''} disabled={index > 0 && levels[index].length === 0}
            onChange={event => onChange(event.target.value || path[index - 1] || '')}
            className={`${className} disabled:bg-gray-50 disabled:text-gray-400`} required={index === 0}>
            <option value="">{index === 0 ? 'Select category' : levels[index].length ? 'Keep product in parent category' : 'No subcategories available'}</option>
            {levels[index].map(category => <option key={category.id} value={category.id}>{category.name}{category.is_active === false ? ' (inactive)' : ''}</option>)}
            {index === 0 && selected && !roots.some(category => category.id === path[0]) && <option value={path[0]}>{selected.label || selected.name} (existing path)</option>}
          </select>
        </label>
      ))}
      <p className="text-xs text-gray-500">{selected ? `Assigned to: ${selected.label || selected.name}` : 'Choose a category. You may stop at any level.'}</p>
    </div>
  );
}
