<?php

namespace Src\Domain\Dashboard\Http\Requests\ConfigureDomain;

use Illuminate\Validation\Rule;
use Src\Infrastructure\Http\AbstractRequests\BaseRequest as FormRequest;
use theaddresstechnology\DDD\Helper\Path;

class ConfigureDomainFormRequest extends FormRequest
{
    /**
     * Determine if the User is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $data=Path::getDomains();
        $rules = [
            'name' => ['required', 'string',Rule::in($data)],
        ];
        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name'        =>"Name",
        ];
    }
}

