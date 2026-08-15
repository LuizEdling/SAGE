import { ParameterType } from './enums';

export interface FunctionParameter {
  id: number;
  name: string;
  type: ParameterType;
  required: boolean;
  description: string | null;
}

export type FunctionParameterPayload = Omit<FunctionParameter, 'id'>;
